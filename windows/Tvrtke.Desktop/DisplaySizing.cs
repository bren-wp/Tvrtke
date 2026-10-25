using System.Windows;
using System.Windows.Interop;

namespace Tvrtke.Desktop;

/// <summary>
/// Converts the working area of the monitor hosting this window from physical
/// pixels to WPF device-independent pixels. Unlike SystemParameters.WorkArea,
/// it also supports secondary monitors with different DPI scaling.
/// </summary>
internal static class DisplaySizing
{
    public static Rect WorkArea(Window window)
    {
        var windowHandle = new WindowInteropHelper(window).EnsureHandle();
        var desktop = System.Windows.Forms.Screen.FromHandle(windowHandle).WorkingArea;
        var transform = PresentationSource.FromVisual(window)?.CompositionTarget?.TransformFromDevice;
        if (transform is null)
        {
            return SystemParameters.WorkArea;
        }
        var topLeft = transform.Value.Transform(new Point(desktop.Left, desktop.Top));
        var bottomRight = transform.Value.Transform(new Point(desktop.Right, desktop.Bottom));
        return new Rect(topLeft, bottomRight);
    }
}
