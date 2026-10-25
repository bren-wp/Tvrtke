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
    private readonly string _api = (Environment.GetEnvironmentVariable("TVRTKE_API_URL") ?? "https://api.tvrtke.com.hr").TrimEnd('/');

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
    }

    private static string Param(string value) => Uri.EscapeDataString(value.Trim());

    private async Task SearchAsync(int? requestedPage = null)
    {
        if (_busy) return;
        _busy = true;
        StatusText.Text = "Dohvaćanje podataka…";
        try
        {
            var query = QueryInput.Text.Trim();
            var county = CountyInput.Text.Trim();
            var activity = ActivityInput.Text.Trim();
            if (new[] { query, county, activity }.Any(v => v.Length > 100))
            {
                StatusText.Text = "Pojedini kriterij smije sadržavati najviše 100 znakova.";
                return;
            }
            var status = (StatusInput.SelectedItem as ComboBoxItem)?.Tag?.ToString() ?? "";
            var pageToLoad = requestedPage ?? 1;
            if (!Uri.TryCreate(_api, UriKind.Absolute, out var apiUrl) || apiUrl.Scheme != Uri.UriSchemeHttps)
            {
                StatusText.Text = "API mora koristiti valjanu HTTPS adresu (TVRTKE_API_URL).";
                return;
            }
            var uri = $"{_api}/v1/subjects?q={Param(query)}&county={Param(county)}&activity={Param(activity)}&status={Param(status)}&page={pageToLoad}&limit=20";
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
            StatusText.Text = $"Prikazano {_results.Count} · snimka {data.SnapshotId ?? "nije dostupna"}" +
                              (data.Partial ? " · djelomični uvoz" : "");
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
            var uri = $"{_api}/v1/subjects/{Param(selected.Mbs)}";
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
        if (value.Length > 0 && ("=+-@\t\r".Contains(value[0]))) value = "'" + value;
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
            var content = new StringBuilder("Naziv;MBS;OIB;Status;Pravni oblik;Mjesto;Županija\r\n");
            foreach (var item in _results)
            {
                content.AppendJoin(';', new[]
                {
                    CsvCell(item.Name), CsvCell(item.Mbs), CsvCell(item.Oib),
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
    [JsonPropertyName("mbs")] public string Mbs { get; set; } = "";
    [JsonPropertyName("oib")] public string? Oib { get; set; }
    [JsonPropertyName("name")] public string? Name { get; set; }
    [JsonPropertyName("county")] public string? County { get; set; }
    [JsonPropertyName("settlement")] public string? Settlement { get; set; }
    [JsonPropertyName("street")] public string? Street { get; set; }
    [JsonPropertyName("house_number")] public string? HouseNumber { get; set; }
    [JsonPropertyName("legal_form")] public string? LegalForm { get; set; }
    [JsonPropertyName("legal_form_short")] public string? LegalFormShort { get; set; }
    [JsonPropertyName("status")] public int Status { get; set; }
    [JsonPropertyName("partial")] public bool Partial { get; set; }
    [JsonPropertyName("activities")] public List<Activity> Activities { get; set; } = [];
    public string Address => string.Join(" ", new[] { Street, HouseNumber }.Where(v => !string.IsNullOrWhiteSpace(v)));
    public string Location => string.Join(", ", new[] { Settlement, County }.Where(v => !string.IsNullOrWhiteSpace(v)));
    public string StatusLabel => Status == 1 ? "Aktivan" : "Neaktivan / brisan";
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
}
