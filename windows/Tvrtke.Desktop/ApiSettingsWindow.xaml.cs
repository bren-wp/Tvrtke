using System.Windows;

namespace Tvrtke.Desktop;

public partial class ApiSettingsWindow : Window
{
    public string ApiUrl { get; private set; }

    public ApiSettingsWindow(string currentUrl)
    {
        InitializeComponent();
        ApiUrl = currentUrl;
        AddressInput.Text = currentUrl;
        AddressInput.SelectAll();
        AddressInput.Focus();
    }

    private void Cancel_Click(object sender, RoutedEventArgs e) => DialogResult = false;

    private void Save_Click(object sender, RoutedEventArgs e)
    {
        var value = AddressInput.Text.Trim().TrimEnd('/');
        if (!Uri.TryCreate(value, UriKind.Absolute, out var uri) ||
            uri.Scheme != Uri.UriSchemeHttps || !string.IsNullOrEmpty(uri.UserInfo) ||
            !string.IsNullOrEmpty(uri.Query) || !string.IsNullOrEmpty(uri.Fragment))
        {
            MessageBox.Show(this, "Unesite valjanu HTTPS adresu bez korisničkih podataka, parametara ili fragmenta.", "TVRTKE");
            return;
        }
        ApiUrl = value;
        DialogResult = true;
    }
}
