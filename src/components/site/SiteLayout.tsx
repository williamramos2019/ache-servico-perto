import type { ReactNode } from "react";
import { Header } from "./Header";
import { Footer } from "./Footer";
import { PWAInstallPrompt } from "./PWAInstallPrompt";

export function SiteLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col bg-background">
      <Header />
      <main className="flex-1">{children}</main>
      <Footer />
      <PWAInstallPrompt />
    </div>
  );
}
