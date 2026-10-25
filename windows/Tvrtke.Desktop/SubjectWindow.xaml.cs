using System.Windows;
using System.Windows.Controls;

namespace Tvrtke.Desktop;

/// <summary>
/// Read-only profile of the selected published legal entity.
/// All displayed values originate from the central search/detail API.
/// </summary>
public partial class SubjectWindow : Window
{
    public sealed record ProfileRow(string Label, string Value);
    public sealed class ProfileContent
    {
        public string Name { get; init; } = "";
        public string SourceLabel { get; init; } = "";
        public string SourceIdentifier { get; init; } = "";
        public string StatusLabel { get; init; } = "";
        public string OibLabel { get; init; } = "";
        public string Address { get; init; } = "";
        public string Location { get; init; } = "";
        public string ProvenanceNote { get; init; } = "";
        public string ActivitiesNote { get; init; } = "";
        public List<Activity> Activities { get; init; } = [];
        public List<ProfileRow> Rows { get; init; } = [];
    }

    public SubjectWindow(Subject subject)
    {
        InitializeComponent();
        var fields = new (string Label, string? Value)[]
        {
            ("Naziv", subject.Name),
            ("OIB", subject.Oib),
            ("MBS", subject.Mbs),
            ("Broj registra", subject.SourceIdentifier),
            ("Pravni oblik", subject.LegalForm),
            ("Status", subject.StatusLabel),
            ("Datum osnivanja", subject.FoundedAt),
            ("Datum upisa", subject.RegisteredAt),
            ("Datum promjene statusa", subject.StatusChangedAt),
            ("Datum brisanja / prestanka", subject.DeletedAt),
        };
        DataContext = new ProfileContent
        {
            Name = subject.Name ?? "Poslovni subjekt",
            OibLabel = subject.Oib is null ? "" : "OIB: " + subject.Oib,
            SourceLabel = subject.SourceLabel,
            SourceIdentifier = subject.SourceIdentifier ?? subject.Mbs ?? "—",
            StatusLabel = subject.StatusLabel,
            Location = string.IsNullOrWhiteSpace(subject.Location) ? "Podatak nije dostupan" : subject.Location,
            Address = string.IsNullOrWhiteSpace(subject.Address) ? "Podatak nije dostupan" : subject.Address,
            ProvenanceNote = subject.Partial
                ? "Registar je objavljen djelomično. Neki javni zapisi i polja mogu nedostajati."
                : "Dostupnost pojedinih polja ovisi o službenom izvoru.",
            ActivitiesNote = subject.Activities.Count == 0
                ? "U objavljenim podacima nema detalja o djelatnostima."
                : "Prikazane su djelatnosti koje javni registar stvarno navodi.",
            Activities = subject.Activities,
            Rows = fields.Select(x => new ProfileRow(x.Label,
                string.IsNullOrWhiteSpace(x.Value) ? "—" : x.Value!)).ToList()
        };
    }

    private void Window_Loaded(object sender, RoutedEventArgs e)
    {
        var work = DisplaySizing.WorkArea(this);
        var w = Math.Max(240, work.Width - 24);
        var h = Math.Max(260, work.Height - 24);
        MinWidth = Math.Min(390, w);
        MinHeight = Math.Min(390, h);
        Width = Math.Clamp(Math.Min(960, work.Width * .87), MinWidth, w);
        Height = Math.Clamp(Math.Min(730, work.Height * .83), MinHeight, h);
        Left = Math.Clamp((Owner?.Left ?? work.Left) + ((Owner?.ActualWidth ?? work.Width) - Width) / 2,
            work.Left, Math.Max(work.Left, work.Right - Width));
        Top = Math.Clamp((Owner?.Top ?? work.Top) + ((Owner?.ActualHeight ?? work.Height) - Height) / 2,
            work.Top, Math.Max(work.Top, work.Bottom - Height));
    }

    private void Window_SizeChanged(object sender, SizeChangedEventArgs e)
    {
        if (InfoGrid is null || LocationCard is null) return;
        var stacked = ActualWidth < 700;
        InfoGrid.ColumnDefinitions[0].Width = new GridLength(1, GridUnitType.Star);
        InfoGrid.ColumnDefinitions[1].Width = new GridLength(stacked ? 0 : 14);
        InfoGrid.ColumnDefinitions[2].Width = stacked ? new GridLength(0) : new GridLength(1, GridUnitType.Star);
        InfoGrid.RowDefinitions[1].Height = stacked ? GridLength.Auto : new GridLength(0);
        Grid.SetRow(LocationCard, stacked ? 1 : 0);
        Grid.SetColumn(LocationCard, stacked ? 0 : 2);
        LocationCard.Margin = stacked ? new Thickness(0, 12, 0, 0) : new Thickness(0);
        CompanyName.FontSize = ActualWidth < 540 ? 19 : 27;
    }

    private void Close_Click(object sender, RoutedEventArgs e) => Close();
}
