import type { Metadata, Viewport } from "next";
import { headers } from "next/headers";
import { Fraunces, Outfit } from "next/font/google";
import "./globals.css";
import "./styles/public-site.css";
import "./styles/meeting-entry.css";

const outfit = Outfit({
  subsets: ["latin"],
  variable: "--font-outfit",
  display: "swap"
});

// Editorial display face for the public marketing site headlines only
// (see public-site.css). Dashboard/app-shell typography is untouched.
const fraunces = Fraunces({
  subsets: ["latin"],
  variable: "--font-fraunces",
  display: "swap"
});

export const metadata: Metadata = {
  title: "Intelli Cash | VSLA Digitisation Platform",
  description:
    "Intelli Cash digitises savings groups with secure meetings, digital passbooks, partner reporting, audit trails, and credit-readiness intelligence.",
  applicationName: "Intelli-Cash Group Account",
  manifest: "/manifest.webmanifest",
  icons: {
    icon: [
      { url: "/brand/intelli-cash-logo.png" },
      { url: "/pwa/icon-192.png", sizes: "192x192", type: "image/png" },
      { url: "/pwa/icon-512.png", sizes: "512x512", type: "image/png" }
    ],
    apple: [{ url: "/pwa/apple-touch-icon.png", sizes: "180x180", type: "image/png" }]
  },
  appleWebApp: {
    capable: true,
    title: "Intelli-Cash Group Account",
    statusBarStyle: "default"
  }
};

export const viewport: Viewport = {
  themeColor: "#1f7a36"
};

// A per-request CSP nonce cannot be stamped into HTML that was generated at
// build time, so nonce-based CSP requires dynamic rendering. Without this the
// nonce lands in the header but not on the script tags, and the page loads
// looking correct while React never hydrates.
export const dynamic = "force-dynamic";

const themeInitializer = `
(() => {
  try {
    const key = "intellicash-theme";
    const getItem = window.localStorage && window.localStorage.getItem;
    const stored = typeof getItem === "function" ? getItem.call(window.localStorage, key) : null;
    const theme = stored === "dark" || stored === "light"
      ? stored
      : (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = theme;
    const themeColor = document.querySelector('meta[name="theme-color"]');
    if (themeColor) themeColor.setAttribute("content", theme === "dark" ? "#07110c" : "#1f7a36");
  } catch {
    document.documentElement.dataset.theme = "light";
  }
})();
`;

export default async function RootLayout({
  children
}: Readonly<{
  children: React.ReactNode;
}>) {
  // Next stamps its OWN scripts with the nonce automatically, but not a
  // hand-written <script>. Without this the theme initialiser is the one tag
  // the CSP blocks, and the app loads with the wrong colour scheme.
  const nonce = (await headers()).get("x-nonce") ?? undefined;

  return (
    <html lang="en" className={`${outfit.variable} ${fraunces.variable}`} suppressHydrationWarning>
      <head>
        <script nonce={nonce} dangerouslySetInnerHTML={{ __html: themeInitializer }} />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-title" content="Intelli-Cash" />
        <meta name="apple-mobile-web-app-status-bar-style" content="default" />
        <meta name="color-scheme" content="light dark" />
        <link rel="apple-touch-startup-image" href="/pwa/splash-828x1792.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2)" />
        <link rel="apple-touch-startup-image" href="/pwa/splash-1125x2436.png" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3)" />
        <link rel="apple-touch-startup-image" href="/pwa/splash-1170x2532.png" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)" />
        <link rel="apple-touch-startup-image" href="/pwa/splash-1242x2688.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3)" />
      </head>
      <body>{children}</body>
    </html>
  );
}
