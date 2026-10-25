using System.Net.Http;
using System.Net.Http.Json;
using System.Text.Json;
using System.Windows;

namespace Tvrtke.Desktop;

public partial class ApiSettingsWindow : Window
{
    private static readonly HttpClient Probe = new(new HttpClientHandler { AllowAutoRedirect = false })
    {
        Timeout = TimeSpan.FromSeconds(9),
    };
    public string ApiUrl { get; private set; }

    public ApiSettingsWindow(string currentUrl)
    {
        InitializeComponent();
        ApiUrl = currentUrl;
        AddressInput.Text = currentUrl;
        AddressInput.SelectAll();
        AddressInput.Focus();
    }

    private static bool ValidHttps(string value) =>
        Uri.TryCreate(value, UriKind.Absolute, out var uri) &&
        uri.Scheme == Uri.UriSchemeHttps &&
        string.IsNullOrEmpty(uri.UserInfo) &&
        string.IsNullOrEmpty(uri.Query) &&
        string.IsNullOrEmpty(uri.Fragment);

    private async void Test_Click(object sender, RoutedEventArgs e)
    {
        var input = AddressInput.Text.Trim().TrimEnd('/');
        if (!ValidHttps(input))
        {
            ConnectionStatus.Text = "Unesite valjanu HTTPS adresu bez korisničkih podataka ili parametara.";
            return;
        }
        TestButton.IsEnabled = false;
        ConnectionStatus.Text = "Provjeravam dostupnost javnog API-ja…";
        try
        {
            using var reply = await Probe.GetAsync(input + "/v1/health");
            reply.EnsureSuccessStatusCode();
            using var health = await reply.Content.ReadFromJsonAsync<JsonDocument>();
            if (health?.RootElement.TryGetProperty("service", out var service) != true ||
                service.GetString() != "tvrtke")
            {
                throw new InvalidOperationException("Odgovor nije TVRTKE API.");
            }
            using var sourcesReply = await Probe.GetAsync(input + "/v1/sources");
            sourcesReply.EnsureSuccessStatusCode();
            using var sources = await sourcesReply.Content.ReadFromJsonAsync<JsonDocument>();
            var count = 0;
            if (sources?.RootElement.TryGetProperty("sources", out var entries) == true &&
                entries.ValueKind == JsonValueKind.Array)
            {
                count = entries.GetArrayLength();
            }
            ConnectionStatus.Text = $"✓ API odgovara. Vanjskih objavljenih izvora: {count}. " +
                "Potpunost i Sudreg stanje provjerite prema javnim metapodacima baze.";
        }
        catch (Exception error) when (error is HttpRequestException or TaskCanceledException or
                                      JsonException or InvalidOperationException)
        {
            ConnectionStatus.Text = "Provjera nije uspjela: adresa je nedostupna ili nije kompatibilan TVRTKE API.";
        }
        finally { TestButton.IsEnabled = true; }
    }

    private void Cancel_Click(object sender, RoutedEventArgs e) => DialogResult = false;

    private void Save_Click(object sender, RoutedEventArgs e)
    {
        var value = AddressInput.Text.Trim().TrimEnd('/');
        if (!ValidHttps(value))
        {
            MessageBox.Show(this, "Unesite valjanu HTTPS adresu bez korisničkih podataka, parametara ili fragmenta.", "TVRTKE");
            return;
        }
        ApiUrl = value;
        DialogResult = true;
    }
}
