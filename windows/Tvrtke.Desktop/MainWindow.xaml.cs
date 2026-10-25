using System.Collections.ObjectModel;
using System.ComponentModel;
using System.Net.Http;
using System.Net.Http.Json;
using System.Text.Json.Serialization;
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

    private async Task SearchAsync()
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
            var uri = $"{_api}/v1/subjects?q={Param(query)}&county={Param(county)}&activity={Param(activity)}&status={Param(status)}&page={_page}&limit=20";
            using var response = await Client.GetAsync(uri);
            response.EnsureSuccessStatusCode();
            var data = await response.Content.ReadFromJsonAsync<SubjectPage>();
            if (data is null) throw new InvalidOperationException("Nije primljen valjan odgovor API-ja.");
            _detailRequest?.Cancel();
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
        catch (Exception ex) when (ex is HttpRequestException or TaskCanceledException or InvalidOperationException)
        {
            StatusText.Text = "API nije dostupan. Provjerite internetsku vezu i konfiguraciju.";
            NextButton.IsEnabled = false;
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
        catch (Exception ex) when (ex is HttpRequestException or InvalidOperationException)
        {
            if (!token.IsCancellationRequested) StatusText.Text = "Detalji trenutačno nisu dostupni.";
        }
    }

    private async void Search_Click(object sender, RoutedEventArgs e) { _page = 1; await SearchAsync(); }
    private async void QueryInput_KeyDown(object sender, KeyEventArgs e)
    {
        if (e.Key == Key.Enter) { _page = 1; await SearchAsync(); }
    }
    private async void Previous_Click(object sender, RoutedEventArgs e)
    {
        if (_page > 1) { _page--; await SearchAsync(); }
    }
    private async void Next_Click(object sender, RoutedEventArgs e)
    {
        if (_hasMore) { _page++; await SearchAsync(); }
    }
    private async void ResultsGrid_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        var selected = ResultsGrid.SelectedItem as Subject;
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
