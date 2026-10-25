using System.Collections.ObjectModel;
using System.ComponentModel;
using System.Net.Http;
using System.Net.Http.Json;
using System.Runtime.CompilerServices;
using System.Text.Json.Serialization;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;

namespace Tvrtke.Desktop;

public partial class MainWindow : Window, INotifyPropertyChanged
{
    private static readonly HttpClient Client = new() { Timeout = TimeSpan.FromSeconds(12) };
    private readonly ObservableCollection<Subject> _results = [];
    private int _page = 1;
    private bool _hasMore;
    private bool _busy;
    private Subject? _selected;

    // Can be overridden with a user-scoped environment variable without rebuilding.
    private readonly string _api = (Environment.GetEnvironmentVariable("TVRTKE_API_URL") ??
        "https://api.tvrtke.com.hr").TrimEnd('/');

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

    private async Task SearchAsync()
    {
        if (_busy) return;
        _busy = true;
        StatusText.Text = "Dohvaćanje podataka…";
        try
        {
            var query = QueryInput.Text.Trim();
            if (query.Length > 100)
            {
                StatusText.Text = "Upit smije sadržavati najviše 100 znakova.";
                return;
            }
            var uri = $"{_api}/v1/subjects?q={Uri.EscapeDataString(query)}&page={_page}&limit=20";
            using var response = await Client.GetAsync(uri);
            response.EnsureSuccessStatusCode();
            var page = await response.Content.ReadFromJsonAsync<SubjectPage>();
            if (page is null) throw new InvalidOperationException("Nije primljen valjan odgovor API-ja.");
            _results.Clear();
            foreach (var subject in page.Items) _results.Add(subject);
            SelectedSubject = null;
            _hasMore = page.HasMore;
            PreviousButton.IsEnabled = _page > 1;
            NextButton.IsEnabled = _hasMore;
            PageText.Text = _page.ToString();
            StatusText.Text = $"Prikazano {_results.Count} rezultata · snimka {page.SnapshotId ?? "nije dostupna"}";
        }
        catch (Exception ex) when (ex is HttpRequestException or TaskCanceledException or InvalidOperationException)
        {
            StatusText.Text = "API nije dostupan. Provjerite konfiguraciju i internetsku vezu.";
            NextButton.IsEnabled = false;
        }
        finally { _busy = false; }
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
    private void ResultsGrid_SelectionChanged(object sender, SelectionChangedEventArgs e) =>
        SelectedSubject = ResultsGrid.SelectedItem as Subject;
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
    public string Address => string.Join(" ", new[] { Street, HouseNumber }.Where(v => !string.IsNullOrWhiteSpace(v)));
}
public sealed class SubjectPage
{
    [JsonPropertyName("items")] public List<Subject> Items { get; set; } = [];
    [JsonPropertyName("has_more")] public bool HasMore { get; set; }
    [JsonPropertyName("snapshot_id")] public string? SnapshotId { get; set; }
}
