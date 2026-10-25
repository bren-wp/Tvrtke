using System.IO;
using System.Text.Json;

namespace Tvrtke.Desktop;

/// <summary>Per-user preferences. No OAuth2 credentials or login passwords are stored here.</summary>
public sealed class TvrtkePreferences
{
    public string ApiUrl { get; set; } = "https://api.tvrtke.com.hr";
    public List<Subject> Favorites { get; set; } = [];

    private static string FilePath
    {
        get
        {
            var directory = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Brendigo", "TVRTKE");
            return Path.Combine(directory, "settings.json");
        }
    }

    public static TvrtkePreferences Load()
    {
        try
        {
            if (!File.Exists(FilePath)) return new TvrtkePreferences();
            var data = JsonSerializer.Deserialize<TvrtkePreferences>(File.ReadAllText(FilePath));
            if (data is null) return new TvrtkePreferences();
            data.Favorites ??= [];
            if (!Uri.TryCreate(data.ApiUrl, UriKind.Absolute, out var uri) || uri.Scheme != Uri.UriSchemeHttps ||
                !string.IsNullOrEmpty(uri.UserInfo) || !string.IsNullOrEmpty(uri.Query) ||
                !string.IsNullOrEmpty(uri.Fragment)) data.ApiUrl = "https://api.tvrtke.com.hr";
            return data;
        }
        catch (Exception ex) when (ex is IOException or UnauthorizedAccessException or JsonException)
        {
            return new TvrtkePreferences();
        }
    }

    public void Save()
    {
        var target = FilePath;
        Directory.CreateDirectory(Path.GetDirectoryName(target)!);
        var temporary = target + ".tmp";
        try
        {
            File.WriteAllText(temporary, JsonSerializer.Serialize(this, new JsonSerializerOptions { WriteIndented = true }));
            File.Move(temporary, target, true);
        }
        finally
        {
            if (File.Exists(temporary)) File.Delete(temporary);
        }
    }
}
