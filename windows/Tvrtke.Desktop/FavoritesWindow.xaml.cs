using System.Collections.ObjectModel;
using System.IO;
using System.Text;
using System.Windows;
using System.Windows.Controls;
using Microsoft.Win32;

namespace Tvrtke.Desktop;

/// <summary>
/// A local-only business-list manager. This screen never calls the central API
/// and never stores OAuth, MySQL or other server credentials.
/// </summary>
public partial class FavoritesWindow : Window
{
    private readonly TvrtkePreferences _preferences;
    private readonly ObservableCollection<Subject> _visible = [];
    private bool? _stacked;

    public FavoritesWindow(TvrtkePreferences preferences)
    {
        _preferences = preferences;
        InitializeComponent();
        FavoritesGrid.ItemsSource = _visible;
        SavedListsBox.ItemsSource = _preferences.SavedLists;
        RefreshFavorites();
    }

    private void Window_Loaded(object sender, RoutedEventArgs e)
    {
        var desktop = DisplaySizing.WorkArea(this);
        var maxWidth = Math.Max(220d, desktop.Width - 24d);
        var maxHeight = Math.Max(240d, desktop.Height - 24d);
        MinWidth = Math.Min(520d, maxWidth);
        MinHeight = Math.Min(470d, maxHeight);
        Width = Math.Clamp(Math.Min(940d, desktop.Width * .88d), MinWidth, maxWidth);
        Height = Math.Clamp(Math.Min(685d, desktop.Height * .83d), MinHeight, maxHeight);
        if (Owner is not null)
        {
            Left = Math.Clamp(Owner.Left + (Owner.ActualWidth - Width) / 2,
                              desktop.Left, Math.Max(desktop.Left, desktop.Right - Width));
            Top = Math.Clamp(Owner.Top + (Owner.ActualHeight - Height) / 2,
                             desktop.Top, Math.Max(desktop.Top, desktop.Bottom - Height));
        }
    }

    private void Window_SizeChanged(object sender, SizeChangedEventArgs e)
    {
        if (Panels is null) return;
        var stacked = ActualWidth < 760;
        if (_stacked == stacked) return;
        _stacked = stacked;
        Panels.ColumnDefinitions[0].Width = new GridLength(
            stacked ? 1 : 1.45, GridUnitType.Star);
        Panels.ColumnDefinitions[1].Width = new GridLength(stacked ? 0 : 13);
        Panels.ColumnDefinitions[2].Width = stacked ? new GridLength(0)
            : new GridLength(1, GridUnitType.Star);
        Panels.RowDefinitions[1].Height = stacked ? GridLength.Auto : new GridLength(0);
        Grid.SetRow(ListsCard, stacked ? 1 : 0);
        Grid.SetColumn(ListsCard, stacked ? 0 : 2);
        ListsCard.Margin = stacked ? new Thickness(0, 12, 0, 0) : new Thickness(0);
    }

    private void RefreshFavorites()
    {
        if (FilterText is null || FavoriteCount is null) return;
        var search = FilterText.Text.Trim();
        _visible.Clear();
        foreach (var favorite in _preferences.Favorites
                     .Where(item =>
                         string.IsNullOrEmpty(search) ||
                         (item.Name?.Contains(search, StringComparison.CurrentCultureIgnoreCase) ?? false) ||
                         (item.Settlement?.Contains(search, StringComparison.CurrentCultureIgnoreCase) ?? false) ||
                         (item.SourceIdentifier?.Contains(search, StringComparison.OrdinalIgnoreCase) ?? false))
                     .OrderBy(item => item.Name, StringComparer.CurrentCultureIgnoreCase))
        {
            _visible.Add(favorite);
        }
        FavoriteCount.Text = $"{_visible.Count} / {_preferences.Favorites.Count}";
        ExportButton.IsEnabled = _visible.Count > 0;
        UpdateButtons();
    }

    private void UpdateButtons()
    {
        if (RemoveFavoriteButton is null || AddToListButton is null || DeleteListButton is null) return;
        RemoveFavoriteButton.IsEnabled = FavoritesGrid.SelectedItem is Subject;
        AddToListButton.IsEnabled =
            FavoritesGrid.SelectedItem is Subject && SavedListsBox.SelectedItem is TvrtkeSavedList;
        DeleteListButton.IsEnabled = SavedListsBox.SelectedItem is TvrtkeSavedList;
    }

    private void Filter_TextChanged(object sender, TextChangedEventArgs e) => RefreshFavorites();
    private void Favorites_SelectionChanged(object sender, SelectionChangedEventArgs e) => UpdateButtons();
    private void SavedLists_SelectionChanged(object sender, SelectionChangedEventArgs e) => UpdateButtons();

    private bool Save()
    {
        try
        {
            _preferences.Save();
            SavedListsBox.Items.Refresh();
            return true;
        }
        catch (Exception error) when (error is IOException or UnauthorizedAccessException)
        {
            StatusText.Text = "Nije moguće spremiti lokalne liste. Provjerite dopuštenja mape profila.";
            return false;
        }
    }

    private void CreateList_Click(object sender, RoutedEventArgs e)
    {
        var name = NewListName.Text.Trim();
        if (name.Length is < 1 or > 60)
        {
            StatusText.Text = "Naziv liste mora imati između 1 i 60 znakova.";
            return;
        }
        if (_preferences.SavedLists.Any(item =>
                string.Equals(item.Name, name, StringComparison.CurrentCultureIgnoreCase)))
        {
            StatusText.Text = "Lista s tim nazivom već postoji.";
            return;
        }
        var list = new TvrtkeSavedList { Name = name };
        _preferences.SavedLists.Add(list);
        if (!Save())
        {
            _preferences.SavedLists.Remove(list);
            return;
        }
        NewListName.Clear();
        SavedListsBox.SelectedItem = list;
        StatusText.Text = $"Spremljena lista: {name}";
    }

    private void AddToList_Click(object sender, RoutedEventArgs e)
    {
        if (FavoritesGrid.SelectedItem is not Subject favorite ||
            SavedListsBox.SelectedItem is not TvrtkeSavedList list) return;
        var key = favorite.UniqueKey;
        if (string.IsNullOrWhiteSpace(key)) return;
        list.RegistryKeys ??= [];
        if (list.RegistryKeys.Contains(key, StringComparer.Ordinal))
        {
            StatusText.Text = "Subjekt se već nalazi u odabranoj listi.";
            return;
        }
        list.RegistryKeys.Add(key);
        if (!Save())
        {
            list.RegistryKeys.Remove(key);
            return;
        }
        StatusText.Text = "Subjekt je dodan u spremljenu listu.";
    }

    private void DeleteList_Click(object sender, RoutedEventArgs e)
    {
        if (SavedListsBox.SelectedItem is not TvrtkeSavedList list) return;
        var choice = MessageBox.Show(this,
            $"Izbrisati listu „{list.Name}”? Favoriti ostaju spremljeni.",
            "TVRTKE", MessageBoxButton.YesNo, MessageBoxImage.Question);
        if (choice != MessageBoxResult.Yes) return;
        _preferences.SavedLists.Remove(list);
        if (!Save()) _preferences.SavedLists.Add(list);
        else StatusText.Text = "Lista je izbrisana. Favoriti nisu obrisani.";
    }

    private void RemoveFavorite_Click(object sender, RoutedEventArgs e)
    {
        if (FavoritesGrid.SelectedItem is not Subject favorite) return;
        var index = _preferences.Favorites.FindIndex(item => item.UniqueKey == favorite.UniqueKey);
        if (index < 0) return;
        _preferences.Favorites.RemoveAt(index);
        var affected = new List<TvrtkeSavedList>();
        foreach (var list in _preferences.SavedLists)
        {
            if (list.RegistryKeys?.Remove(favorite.UniqueKey) == true) affected.Add(list);
        }
        if (!Save())
        {
            _preferences.Favorites.Insert(index, favorite);
            foreach (var list in affected) list.RegistryKeys.Add(favorite.UniqueKey);
        }
        else StatusText.Text = "Favorit je uklonjen iz svih spremljenih lista.";
        RefreshFavorites();
    }

    private static string Csv(string? value)
    {
        var raw = value ?? string.Empty;
        var leading = raw.TrimStart(' ', '\t', '\r', '\n');
        if (leading.Length > 0 && "=+-@".Contains(leading[0])) raw = "'" + raw;
        return "\"" + raw.Replace("\"", "\"\"") + "\"";
    }

    private void Export_Click(object sender, RoutedEventArgs e)
    {
        if (_visible.Count == 0) return;
        var dialog = new SaveFileDialog
        {
            Filter = "CSV datoteka (*.csv)|*.csv",
            FileName = "TVRTKE-favoriti.csv",
            DefaultExt = ".csv",
            AddExtension = true
        };
        if (dialog.ShowDialog(this) != true) return;
        try
        {
            var data = new StringBuilder("Naziv;Izvor;Broj registra;OIB;Mjesto;Status\r\n");
            foreach (var row in _visible)
            {
                data.AppendJoin(';', [
                    Csv(row.Name), Csv(row.SourceLabel), Csv(row.SourceIdentifier),
                    Csv(row.Oib), Csv(row.Settlement), Csv(row.StatusLabel)
                ]);
                data.Append("\r\n");
            }
            File.WriteAllText(dialog.FileName, data.ToString(), new UTF8Encoding(true));
            StatusText.Text = $"Izvezeno {_visible.Count} favorita u CSV.";
        }
        catch (Exception error) when (error is IOException or UnauthorizedAccessException)
        {
            StatusText.Text = "Izvoz nije uspio. Provjerite mapu i slobodan prostor.";
        }
    }

    private void Close_Click(object sender, RoutedEventArgs e) => Close();
}
