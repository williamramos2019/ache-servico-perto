import type { ReactNode } from "react";
import { Header } from "./Header";
import { Footer } from "./Footer";
import { PWAInstallPrompt } from "./PWAInstallPrompt";
import { SeasonalTheme } from "./SeasonalTheme";
import { useCityAutoDetect } from "@/hooks/useCityAutoDetect";
import { useRouterState } from "@tanstack/react-router";
import { AdSlot, RequestButton } from "./DomainWidgets";
import { useSelectedCity } from "@/hooks/useSelectedCity";

export function SiteLayout({ children }: { children: ReactNode }) {
  useCityAutoDetect();
  const pathname = useRouterState({ select: (state) => state.location.pathname });
  const { city } = useSelectedCity();
  const isPrivateArea = pathname.startsWith("/admin") || pathname.startsWith("/painel") || pathname === "/auth";
  return (
    <div className="flex min-h-screen flex-col bg-background">
      <Header />
      <main className="flex-1">{children}</main>
      {!isPrivateArea && (
        <div className="container mx-auto space-y-4 px-4 pb-8">
          <AdSlot city={city} />
          <div className="flex justify-center"><RequestButton /></div>
        </div>
      )}
      <Footer />
      <PWAInstallPrompt />
      <SeasonalTheme />
    </div>
  );
}
