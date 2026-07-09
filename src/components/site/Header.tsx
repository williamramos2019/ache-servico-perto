import { useState } from "react";
import { Link, useRouterState } from "@tanstack/react-router";
import { Heart, LayoutDashboard, LogOut, MapPin, Menu, Search, ShieldCheck, X } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { useAdmin } from "@/hooks/use-admin";
import { supabase } from "@/integrations/supabase/client";
import { CityPickerDialog } from "./CityPickerDialog";

type NavItem = { to: string; label: string; danger?: boolean };
const NAV: NavItem[] = [
  { to: "/", label: "Início" },
  { to: "/servicos-publicos", label: "Serviços Públicos" },
  { to: "/emergencia", label: "Emergência", danger: true },
  { to: "/buscar", label: "Empresas" },
  { to: "/blog", label: "Blog" },
  { to: "/sobre", label: "Sobre" },
];

export function Header() {
  const { isAdmin, userId } = useAdmin();
  const [open, setOpen] = useState(false);
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const isAuthed = !!userId;

  const isActive = (to: string) => (to === "/" ? pathname === "/" : pathname.startsWith(to));

  async function handleSignOut() {
    const { error } = await supabase.auth.signOut();
    if (error) toast.error("Erro ao sair");
    else toast.success("Você saiu da conta");
    setOpen(false);
  }


  return (
    <header className="sticky top-0 z-40 w-full border-b border-border/70 bg-background/80 backdrop-blur-xl supports-[backdrop-filter]:bg-background/70">
      <div className="container mx-auto flex h-16 items-center justify-between gap-4 px-4">
        <Link to="/" className="flex items-center gap-2.5 group" aria-label="AgendaAqui — Início">
          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary-dark text-primary-foreground shadow-sm transition-transform group-hover:scale-105">
            <MapPin className="h-5 w-5" />
          </div>
          <div className="leading-tight">
            <div className="font-display text-lg font-extrabold tracking-tight text-foreground">
              AgendaAqui
            </div>
            <div className="hidden sm:block text-[10px] uppercase tracking-[0.14em] text-muted-foreground whitespace-nowrap">
              Vespasiano · S. J. da Lapa
            </div>
          </div>
        </Link>

        <nav className="hidden items-center gap-0.5 lg:flex">
          {NAV.map((n) => {
            const active = isActive(n.to);
            return (
              <Link
                key={n.to}
                to={n.to as any}
                search={n.to === "/servicos-publicos" ? ({} as any) : undefined}
                className={[
                  "relative rounded-full px-3.5 py-2 text-sm font-medium transition-colors",
                  n.danger
                    ? "text-destructive hover:bg-destructive/10"
                    : active
                    ? "text-foreground bg-muted"
                    : "text-muted-foreground hover:text-foreground hover:bg-muted/70",
                ].join(" ")}
              >
                {n.label}
              </Link>
            );
          })}
        </nav>

        <div className="flex items-center gap-1.5">
          <div className="hidden md:inline-flex"><CityPickerDialog /></div>
          {isAdmin ? (
            <Link to="/admin" className="hidden lg:inline-flex" aria-label="Admin">
              <Button variant="ghost" size="sm" className="gap-1"><ShieldCheck className="h-4 w-4" /> Admin</Button>
            </Link>
          ) : null}
          {isAuthed ? (
            <Link to="/painel" className="hidden lg:inline-flex" aria-label="Meu painel">
              <Button variant="ghost" size="sm" className="gap-1"><LayoutDashboard className="h-4 w-4" /> Painel</Button>
            </Link>
          ) : null}
          <Link to="/favoritos" aria-label="Favoritos" className="hidden sm:inline-flex">
            <Button variant="ghost" size="icon" className="rounded-full"><Heart className="h-5 w-5" /></Button>
          </Link>
          <Link to="/buscar" className="lg:hidden">
            <Button variant="ghost" size="icon" aria-label="Buscar" className="rounded-full"><Search className="h-5 w-5" /></Button>
          </Link>
          {isAuthed ? (
            <Button variant="ghost" size="sm" className="hidden lg:inline-flex gap-1" onClick={handleSignOut} aria-label="Sair">
              <LogOut className="h-4 w-4" /> Sair
            </Button>
          ) : (
            <Link to="/auth" className="hidden lg:inline-flex">
              <Button variant="ghost" size="sm">Entrar</Button>
            </Link>
          )}
          <Link to="/planos">
            <Button size="sm" className="btn-shine press-scale rounded-full bg-accent px-4 text-accent-foreground shadow-sm transition-all hover:-translate-y-0.5 hover:bg-accent/90 hover:shadow-md">
              Anunciar
            </Button>
          </Link>
          <Button
            variant="ghost"
            size="icon"
            aria-label={open ? "Fechar menu" : "Abrir menu"}
            className="lg:hidden rounded-full"
            onClick={() => setOpen((v) => !v)}
          >
            {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </Button>
        </div>
      </div>

      {/* Mobile menu */}
      {open ? (
        <div className="lg:hidden border-t border-border bg-background/95 backdrop-blur-xl animate-fade-up">
          <nav className="container mx-auto flex flex-col gap-1 px-4 py-3">
            {NAV.map((n) => {
              const active = isActive(n.to);
              return (
                <Link
                  key={n.to}
                  to={n.to as any}
                  search={n.to === "/servicos-publicos" ? ({} as any) : undefined}
                  onClick={() => setOpen(false)}
                  className={[
                    "rounded-lg px-3 py-2.5 text-sm font-medium",
                    n.danger
                      ? "text-destructive hover:bg-destructive/10"
                      : active
                      ? "bg-muted text-foreground"
                      : "text-foreground/80 hover:bg-muted",
                  ].join(" ")}
                >
                  {n.label}
                </Link>
              );
            })}
            <div className="mt-2 flex gap-2 border-t border-border pt-3">
              <Link to="/favoritos" className="flex-1" onClick={() => setOpen(false)}>
                <Button variant="outline" size="sm" className="w-full gap-2"><Heart className="h-4 w-4" /> Favoritos</Button>
              </Link>
              {isAuthed ? (
                <Button variant="outline" size="sm" className="flex-1 gap-2" onClick={handleSignOut}>
                  <LogOut className="h-4 w-4" /> Sair
                </Button>
              ) : (
                <Link to="/auth" className="flex-1" onClick={() => setOpen(false)}>
                  <Button variant="outline" size="sm" className="w-full">Entrar</Button>
                </Link>
              )}
            </div>
          </nav>
        </div>
      ) : null}
    </header>
  );
}
