import { Link } from "@tanstack/react-router";
import { Heart, MapPin, Search, ShieldCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useAdmin } from "@/hooks/use-admin";

export function Header() {
  const { isAdmin } = useAdmin();
  return (
    <header className="sticky top-0 z-40 w-full border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">

      <div className="container mx-auto flex h-16 items-center justify-between gap-4 px-4">
        <Link to="/" className="flex items-center gap-2">
          <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
            <MapPin className="h-5 w-5" />
          </div>
          <div className="leading-tight">
            <div className="font-display text-lg font-extrabold tracking-tight text-foreground">
              AgendaAqui
            </div>
            <div className="text-[10px] uppercase tracking-wider text-muted-foreground">
              Vespasiano · São José da Lapa
            </div>
          </div>
        </Link>

        <nav className="hidden items-center gap-1 md:flex">
          <Link to="/" className="rounded-md px-3 py-2 text-sm font-medium text-foreground/80 hover:text-foreground">Início</Link>
          <Link to="/servicos-publicos" search={{}} className="rounded-md px-3 py-2 text-sm font-medium text-foreground/80 hover:text-foreground">Serviços Públicos</Link>
          <Link to="/emergencia" className="rounded-md px-3 py-2 text-sm font-medium text-destructive hover:text-destructive/80">Emergência</Link>
          <Link to="/buscar" className="rounded-md px-3 py-2 text-sm font-medium text-foreground/80 hover:text-foreground">Empresas</Link>
          <Link to="/blog" className="rounded-md px-3 py-2 text-sm font-medium text-foreground/80 hover:text-foreground">Blog</Link>
          <Link to="/sobre" className="rounded-md px-3 py-2 text-sm font-medium text-foreground/80 hover:text-foreground">Sobre</Link>
        </nav>

        <div className="flex items-center gap-2">
          {isAdmin ? (
            <Link to="/admin" className="hidden md:inline-flex" aria-label="Admin">
              <Button variant="ghost" size="sm" className="gap-1"><ShieldCheck className="h-4 w-4" /> Admin</Button>
            </Link>
          ) : null}
          <Link to="/favoritos" aria-label="Favoritos">
            <Button variant="ghost" size="icon"><Heart className="h-5 w-5" /></Button>
          </Link>
          <Link to="/buscar" className="md:hidden">
            <Button variant="ghost" size="icon" aria-label="Buscar"><Search className="h-5 w-5" /></Button>
          </Link>
          <Link to="/auth" className="hidden md:inline-flex">
            <Button variant="ghost" size="sm">Entrar</Button>
          </Link>
          <Link to="/planos">
            <Button size="sm" className="bg-accent text-accent-foreground hover:bg-accent/90">
              Anunciar
            </Button>
          </Link>
        </div>
      </div>
    </header>
  );
}
