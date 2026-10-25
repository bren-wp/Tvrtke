using System;
using System.Runtime.InteropServices;
using System.Windows;
using System.Windows.Interop;

namespace Tvrtke.Desktop;

/// <summary>
/// Per-monitor working area in WPF DIPs. The user32 API avoids a WinForms
/// dependency (and avoids clashing WinForms/WPF implicit type namespaces).
/// </summary>
internal static class DisplaySizing
{
    private const uint MonitorDefaultToNearest = 2;

    [StructLayout(LayoutKind.Sequential)]
    private struct NativeRect
    {
        public int Left;
        public int Top;
        public int Right;
        public int Bottom;
    }

    [StructLayout(LayoutKind.Sequential)]
    private struct MonitorInfo
    {
        public int Size;
        public NativeRect Monitor;
        public NativeRect Work;
        public uint Flags;
    }

    [DllImport("user32.dll")]
    private static extern IntPtr MonitorFromWindow(IntPtr handle, uint flags);

    [DllImport("user32.dll", CharSet = CharSet.Auto)]
    [return: MarshalAs(UnmanagedType.Bool)]
    private static extern bool GetMonitorInfo(IntPtr monitor, ref MonitorInfo info);

    public static Rect WorkArea(Window window)
    {
        var handle = new WindowInteropHelper(window).EnsureHandle();
        var monitor = MonitorFromWindow(handle, MonitorDefaultToNearest);
        var info = new MonitorInfo { Size = Marshal.SizeOf<MonitorInfo>() };
        if (monitor == IntPtr.Zero || !GetMonitorInfo(monitor, ref info))
            return SystemParameters.WorkArea;

        var transformation = PresentationSource.FromVisual(window)?.CompositionTarget?.TransformFromDevice;
        if (transformation is null) return SystemParameters.WorkArea;

        var a = transformation.Value.Transform(new Point(info.Work.Left, info.Work.Top));
        var b = transformation.Value.Transform(new Point(info.Work.Right, info.Work.Bottom));
        return new Rect(a, b);
    }
}
