using System.Collections.ObjectModel;
using System.ComponentModel;
using System.Net.Http;
using System.Net.Http.Json;
using System.Text;
using Microsoft.Win32;
using System.Text.Json.Serialization;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;

namespace Tvrtke.Desktop;

public partial class MainWindow : Window, INotifyPropertyChanged
{
    private static readonly HttpClient Client = new() { Timeout = TimeSpan.FromSeconds(12) };
    private readonly ObservableCollection<Subject> _results = [];
    private CancellationTokenSource? _detailRequest;
    private int _page = 1;
    private bool _hasMore;
    private bool _busy;
    private Subject? _selected;
    private readonly TvrtkePreferences _preferences = TvrtkePreferences.Load();
    private string _api = "https://api.tvrtke.com.hr";
    private bool _favoritesMode;

    public event PropertyChangedEventHandler? PropertyChanged;
    public Subject? SelectedSubject
    {
        get => _selected;
        set { _selected = value; PropertyChanged?.Invoke(this, new PropertyChangedEventArgs(nameof(SelectedSubject))); }
    }

    public MainWindow()
    {
        InitializeComponent();
        DataContext = this;
        ResultsGrid.ItemsSource = _results;
        _api = (Environment.GetEnvironmentVariable("TVRTKE_API_URL") ?? _preferences.ApiUrl).TrimEnd('/');
    }

    private static string Param(string value) => Uri.EscapeDataString(value.Trim());

    private async Task SearchAsync(int? requestedPage = null)
    {
        if (_busy) return;
        _busy = true;
        StatusText.Text = "Dohvaćanje podataka…";
        try
        {
            _favoritesMode = false;
            FavoritesButton.Content = "Favoriti";
            var query = QueryInput.Text.Trim();
            var county = CountyInput.Text.Trim();
            var activity = ActivityInput.Text.Trim();
            if (new[] { query, county, activity }.Any(v => v.Length > 100))
            {
                StatusText.Text = "Pojedini kriterij smije sadržavati najviše 100 znakova.";
                return;
            }
            var status = (StatusInput.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "";
            var type = (CategoryInput.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "all";
            var pageToLoad = requestedPage ?? 1;
            if (!Uri.TryCreate(_api, UriKind.Absolute, out var apiUrl) || apiUrl.Scheme != Uri.UriSchemeHttps ||
                !string.IsNullOrEmpty(apiUrl.UserInfo) || !string.IsNullOrEmpty(apiUrl.Query) ||
                !string.IsNullOrEmpty(apiUrl.Fragment))
            {
                StatusText.Text = "API mora koristiti valjanu HTTPS adresu (TVRTKE_API_URL).";
                return;
            }
            var uri = $"{_api}/v1/directory?q={Param(query)}&type={Param(type)}&county={Param(county)}&activity={Param(activity)}&status={Param(status)}&page={pageToLoad}&limit=20";
            using var response = await Client.GetAsync(uri);
            response.EnsureSuccessStatusCode();
            var data = await response.Content.ReadFromJsonAsync<SubjectPage>();
            if (data is null) throw new InvalidOperationException("Nije primljen valjan odgovor API-ja.");
            _detailRequest?.Cancel();
            _page = pageToLoad; // Update the current page only AFTER a successful request.
            _results.Clear();
            foreach (var item in data.Items) _results.Add(item);
            SelectedSubject = null;
            _hasMore = data.HasMore;
            PreviousButton.IsEnabled = _page > 1;
            NextButton.IsEnabled = _hasMore;
            PageText.Text = _page.ToString();
            StatusText.Text = $"Prikazano {_results.Count} zapisa · izvora: {data.Sources.Count + (data.SnapshotId is null ? 0 : 1)}" +
                              (data.Partial ? " · baza nije potpuna" : "");
        }
        catch (Exception ex) when (ex is HttpRequestException or TaskCanceledException or InvalidOperationException or JsonException)
        {
            StatusText.Text = "API nije dostupan ili je poslao neispravan odgovor.";
        }
        finally { _busy = false; }
    }

    private async Task LoadDetailsAsync(Subject selected)
    {
        _detailRequest?.Cancel();
        _detailRequest?.Dispose();
        _detailRequest = new CancellationTokenSource();
        var token = _detailRequest.Token;
        try
        {
            var key = selected.RegistryKey;
            if (string.IsNullOrWhiteSpace(key) && selected.Mbs?.Length == 9) key = "sudreg:" + selected.Mbs;
            var parts = key.Split(':', 2);
            if (parts.Length != 2 || !new[] { "sudreg", "obrt", "udruga", "zaklada", "ostalo" }.Contains(parts[0]) ||
                !System.Text.RegularExpressions.Regex.IsMatch(parts[1], @"^([a-f0-9]{64}|[0-9]{9})$"))
            {
                StatusText.Text = "Za ovaj subjekt nije dostupan valjan identifikator.";
                return;
            }
            var uri = $"{_api}/v1/directory/{Param(parts[0])}/{Param(parts[1])}";
            using var response = await Client.GetAsync(uri, token);
            response.EnsureSuccessStatusCode();
            var details = await response.Content.ReadFromJsonAsync<SubjectDetail>(cancellationToken: token);
            if (!token.IsCancellationRequested && ResultsGrid.SelectedItem == selected && details?.Item is not null)
                SelectedSubject = details.Item;
        }
        catch (OperationCanceledException) { /* Changing the selection cancels an obsolete fetch. */ }
        catch (Exception ex) when (ex is HttpRequestException or InvalidOperationException or JsonException)
        {
            if (!token.IsCancellationRequested) StatusText.Text = "Detalji trenutačno nisu dostupni.";
        }
    }

    // Export only the currently displayed page. Spreadsheet formula injection is neutralized.
    private static string CsvCell(string? text)
    {
        var value = text ?? "";
        var leading = value.TrimStart(' ', '\t', '\r', '\n');
        if (leading.Length > 0 && "=+-@".Contains(leading[0])) value = "'" + value;
        return "\"" + value.Replace("\"", "\"\"") + "\"";
    }

    private void Export_Click(object sender, RoutedEventArgs e)
    {
        if (_results.Count == 0)
        {
            StatusText.Text = "Nema prikazanih rezultata za izvoz.";
            return;
        }
        var dialog = new SaveFileDialog
        {
            Filter = "CSV datoteka (*.csv)|*.csv",
            FileName = $"TVRTKE-rezultati-stranica-{_page}.csv",
            DefaultExt = ".csv",
            AddExtension = true
        };
        if (dialog.ShowDialog(this) != true) return;
        try
        {
            var content = new StringBuilder("Naziv;Izvor;Broj registra;MBS;OIB;Status;Pravni oblik;Mjesto;Županija\r\n");
            foreach (var item in _results)
            {
                content.AppendJoin(';', new[]
                {
                    CsvCell(item.Name), CsvCell(item.SourceLabel), CsvCell(item.SourceIdentifier),
                    CsvCell(item.Mbs), CsvCell(item.Oib),
                    CsvCell(item.StatusLabel), CsvCell(item.LegalForm),
                    CsvCell(item.Settlement), CsvCell(item.County)
                });
                content.Append("\r\n");
            }
            System.IO.File.WriteAllText(dialog.FileName, content.ToString(), new UTF8Encoding(true));
            StatusText.Text = $"Izvezeno {_results.Count} prikazanih subjekata.";
        }
        catch (Exception ex) when (ex is System.IO.IOException or UnauthorizedAccessException)
        {
            StatusText.Text = "Izvoz nije uspio. Provjerite dopuštenja i slobodni prostor.";
        }
    }

    private void Settings_Click(object sender, RoutedEventArgs e)
    {
        if (!string.IsNullOrWhiteSpace(Environment.GetEnvironmentVariable("TVRTKE_API_URL")))
        {
            MessageBox.Show(this, "API adresa postavljena je varijablom TVRTKE_API_URL i ne može se promijeniti u aplikaciji.", "TVRTKE");
            return;
        }
        var dialog = new ApiSettingsWindow(_api) { Owner = this };
        if (dialog.ShowDialog() != true) return;
        _api = dialog.ApiUrl;
        _preferences.ApiUrl = _api;
        try { _preferences.Save(); }
        catch (Exception ex) when (ex is System.IO.IOException or UnauthorizedAccessException)
        {
            StatusText.Text = "Adresa je promijenjena za ovu sesiju, ali postavke nisu spremljene.";
            return;
        }
        StatusText.Text = "Nova API adresa spremljena. Pokrenite pretragu.";
        _results.Clear();
        _page = 1;
        PreviousButton.IsEnabled = false;
        NextButton.IsEnabled = false;
        SelectedSubject = null;
    }

    private void Favorites_Click(object sender, RoutedEventArgs e)
    {
        _favoritesMode = !_favoritesMode;
        FavoritesButton.Content = _favoritesMode ? "Natrag na pretragu" : "Favoriti";
        _results.Clear();
        if (_favoritesMode)
        {
            foreach (var item in _preferences.Favorites.OrderBy(x => x.Name)) _results.Add(item);
            StatusText.Text = $"Spremljenih favorita: {_results.Count} (lokalno na ovom računalu).";
        }
        else
        {
            StatusText.Text = "Za povratak na rezultate pokrenite novu pretragu.";
        }
        SelectedSubject = null;
        FavoriteButton.IsEnabled = false;
        _page = 1;
        _hasMore = false;
        PreviousButton.IsEnabled = false;
        NextButton.IsEnabled = false;
        PageText.Text = "1";
    }

    private void ToggleFavorite_Click(object sender, RoutedEventArgs e)
    {
        var selected = SelectedSubject;
        if (selected is null || string.IsNullOrWhiteSpace(selected.UniqueKey)) return;
        var previous = _preferences.Favorites.FirstOrDefault(x => x.UniqueKey == selected.UniqueKey);
        if (previous is not null)
        {
            _preferences.Favorites.Remove(previous);
            FavoriteButton.Content = "Dodaj u favorite";
            if (_favoritesMode)  _results.Remove(_results.FirstOrDefault(x => x.UniqueKey == selected.UniqueKey)!);
        }
        else
        {
            _preferences.Favorites.Add(selected);
            FavoriteButton.Content = "Ukloni iz favorita";
        }
        try { _preferences.Save(); StatusText.Text = "Favoriti ažurirani."; }
        catch (Exception ex) when (ex is System.IO.IOException or UnauthorizedAccessException)
        {
            StatusText.Text = "Promjena favorita nije mogla biti spremljena na disk.";
        }
    }

    private async void Search_Click(object sender, RoutedEventArgs e) { await SearchAsync(1); }
    private async void QueryInput_KeyDown(object sender, KeyEventArgs e)
    {
        if (e.Key == Key.Enter) { await SearchAsync(1); }
    }
    private async void Previous_Click(object sender, RoutedEventArgs e)
    {
        if (_page > 1) { await SearchAsync(_page - 1); }
    }
    private async void Next_Click(object sender, RoutedEventArgs e)
    {
        if (_hasMore) { await SearchAsync(_page + 1); }
    }
    private async void ResultsGrid_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        var selected = ResultsGrid.SelectedItem as Subject;
        _detailRequest?.Cancel();
        SelectedSubject = selected;
        FavoriteButton.IsEnabled = selected is not null;
        FavoriteButton.Content = selected is not null && _preferences.Favorites.Any(x => x.UniqueKey == selected.UniqueKey) ? "Ukloni iz favorita" : "Dodaj u favorite";
        if (selected is not null) await LoadDetailsAsync(selected);
    }
}

public sealed class Activity
{
    [JsonPropertyName("activity_text")] public string? Text { get; set; }
    [JsonPropertyName("nkd_name")] public string? NkdName { get; set; }
    [JsonPropertyName("nkd_code")] public string? NkdCode { get; set; }
    public string Display => Text ?? NkdName ?? NkdCode ?? "—";
}

public sealed class Subject
{
    [JsonPropertyName("mbs")] public string? Mbs { get; set; }
    [JsonPropertyName("registry_key")] public string RegistryKey { get; set; } = "";
    [JsonPropertyName("source_type")] public string SourceType { get; set; } = "sudreg";
    [JsonPropertyName("source_identifier")] public string? SourceIdentifier { get; set; }
    [JsonPropertyName("status_label")] public string? ApiStatusLabel { get; set; }
    public string UniqueKey => !string.IsNullOrWhiteSpace(RegistryKey) ? RegistryKey : (Mbs?.Length == 9 ? "sudreg:" + Mbs : "");
    public string SourceLabel => SourceType switch
    {
        "sudreg" => "Sudski registar",
        "obrt" => "Obrt",
        "udruga" => "Udruga",
        "zaklada" => "Zaklada",
        "ostalo" => "Ostalo",
        _ => "Nepoznato"
    };
    [JsonPropertyName("oib")] public string? Oib { get; set; }
    [JsonPropertyName("name")] public string? Name { get; set; }
    [JsonPropertyName("county")] public string? County { get; set; }
    [JsonPropertyName("settlement")] public string? Settlement { get; set; }
    [JsonPropertyName("street")] public string? Street { get; set; }
    [JsonPropertyName("house_number")] public string? HouseNumber { get; set; }
    [JsonPropertyName("legal_form")] public string? LegalForm { get; set; }
    [JsonPropertyName("legal_form_short")] public string? LegalFormShort { get; set; }
    [JsonPropertyName("datum_osnivanja")] public string? FoundedAt { get; set; }
    [JsonPropertyName("datum_brisanja")] public string? DeletedAt { get; set; }
    [JsonPropertyName("status")] public int? Status { get; set; }
    [JsonPropertyName("partial")] public bool Partial { get; set; }
    [JsonPropertyName("activities")] public List<Activity> Activities { get; set; } = [];
    public string Address => string.Join(" ", new[] { Street, HouseNumber }.Where(v => !string.IsNullOrWhiteSpace(v)));
    public string Location => string.Join(", ", new[] { Settlement, County }.Where(v => !string.IsNullOrWhiteSpace(v)));
    public string StatusLabel => ApiStatusLabel ?? (Status == 1 ? "Aktivan" : Status == 0 ? "Neaktivan / brisan" : "Nepoznat");
    public Visibility PartialVisibility => Partial ? Visibility.Visible : Visibility.Collapsed;
}

public sealed class SubjectDetail
{
    [JsonPropertyName("item")] public Subject? Item { get; set; }
}

public sealed class SubjectPage
{
    [JsonPropertyName("items")] public List<Subject> Items { get; set; } = [];
    [JsonPropertyName("has_more")] public bool HasMore { get; set; }
    [JsonPropertyName("snapshot_id")] public string? SnapshotId { get; set; }
    [JsonPropertyName("partial")] public bool Partial { get; set; }
    [JsonPropertyName("sources")] public List<JsonElement> Sources { get; set; } = [];
}
