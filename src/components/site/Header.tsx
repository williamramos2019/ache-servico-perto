import { useState, type ComponentType, type SVGProps } from "react";
import { Link, useNavigate, useRouterState } from "@tanstack/react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  Bell,
  Bus,
  Calendar,
  Briefcase,
  Building2,
  ChevronDown,
  Compass,
  Heart,
  Home,
  LayoutDashboard,
  LogOut,
  MapPin,
  Megaphone,
  Menu,
  Newspaper,
  ShieldCheck,
  ShoppingBag,
  X,
} from "lucide-react";
import { unreadInboxCount } from "@/lib/push.functions";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useAdmin } from "@/hooks/use-admin";
import { logoutSession } from "@/lib/php-auth";
import { CityPickerDialog } from "./CityPickerDialog";
import { HeaderSearch } from "./HeaderSearch";
import { DEFAULT_NAV_ITEMS, fetchNavItems } from "@/lib/navItems";
import { useSiteContent } from "@/lib/siteContent";
import { categoriesQueryOptions } from "@/lib/queries";
import { CategoryIcon } from "./CategoryIcon";

type IconType = ComponentType<SVGProps<SVGSVGElement> & { size?: number | string }>;

const NAV_ICONS: Record<string, IconType> = {
  "/": Home,
  "/blog": Newspaper,
  "/buscar": Building2,
  "/eventos": Calendar,
  "/o-que-fazer": Compass,
  "/marketplace": ShoppingBag,
  "/transporte": Bus,
  "/empregos": Briefcase,
  "/promocoes": Megaphone,
};

function stripLeadingEmoji(label: string): string {
  return label.replace(/^\p{Extended_Pictographic}[\uFE0F\u200D]?\s*/u, "");
}

export function Header() {
  const { data: NAV = DEFAULT_NAV_ITEMS } = useQuery({
    queryKey: ["nav-items"],
    queryFn: fetchNavItems,
    staleTime: 5 * 60_000,
  });
  const cats = useQuery(categoriesQueryOptions);
  const site = useSiteContent();
  const { isAdmin, userId } = useAdmin();
  const [open, setOpen] = useState(false);
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const isAuthed = !!userId;
  const hideMobileSearch = pathname.startsWith("/empresa/") || pathname.startsWith("/auth") || pathname.startsWith("/admin") || pathname.startsWith("/painel");

  const isActive = (to: string) => (to === "/" ? pathname === "/" : pathname.startsWith(to));

  const { data: unread } = useQuery({
    queryKey: ["push-unread"],
    queryFn: () => unreadInboxCount({}),
    enabled: isAuthed,
    refetchInterval: 60_000,
  });
  const unreadCount = unread?.count ?? 0;

  async function handleSignOut() {
    await queryClient.cancelQueries();
    queryClient.clear();
    try {
      await logoutSession();
      toast.success("Você saiu da conta");
    } catch {
      toast.error("Erro ao sair");
    }
    setOpen(false);
    navigate({ to: "/", replace: true });
  }

  return (
    <header className="sticky top-0 z-40 w-full border-b border-border/70 bg-background/90 backdrop-blur-xl supports-[backdrop-filter]:bg-background/80">
      <div className="container mx-auto flex h-14 items-center gap-2 px-3 sm:h-16 sm:gap-3 sm:px-4">
        <Button
          variant="ghost"
          size="icon"
          aria-label={open ? "Fechar menu" : "Abrir menu"}
          className="lg:hidden rounded-full"
          onClick={() => setOpen((v) => !v)}
        >
          {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
        </Button>

        <Link
          to="/"
          className="flex min-w-0 items-center gap-2 group"
          aria-label={`${site.brand.name} — Início`}
        >
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary-dark text-primary-foreground shadow-sm">
            <MapPin className="h-5 w-5" />
          </div>
          <div className="min-w-0 leading-tight">
            <div className="truncate font-display text-base font-extrabold tracking-tight text-foreground sm:text-lg">
              {site.brand.name}
            </div>
          </div>
        </Link>

        <div className="hidden md:block">
          <CityPickerDialog />
        </div>

        <HeaderSearch className="hidden min-w-0 lg:flex" />

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm" className="hidden lg:inline-flex gap-1">
              Categorias <ChevronDown className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="max-h-[70vh] w-64 overflow-y-auto">
            {(cats.data ?? []).map((c) => (
              <DropdownMenuItem key={c.id} asChild>
                <Link to="/categoria/$slug" params={{ slug: c.slug }} className="flex items-center gap-2">
                  <CategoryIcon name={c.icon} className="h-4 w-4 text-primary" />
                  {c.name}
                </Link>
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>

        <div className="ml-auto flex shrink-0 items-center gap-1 sm:gap-1.5">
          {isAdmin ? (
            <Link to="/admin" className="hidden xl:inline-flex" aria-label={site.header.admin_label}>
              <Button variant="ghost" size="sm" className="gap-1">
                <ShieldCheck className="h-4 w-4" /> {site.header.admin_label}
              </Button>
            </Link>
          ) : null}
          {isAuthed ? (
            <Link to="/painel" className="hidden xl:inline-flex" aria-label={site.header.panel_label}>
              <Button variant="ghost" size="sm" className="gap-1">
                <LayoutDashboard className="h-4 w-4" /> {site.header.panel_label}
              </Button>
            </Link>
          ) : null}
          <Link to="/favoritos" aria-label="Favoritos" className="hidden sm:inline-flex">
            <Button variant="ghost" size="icon" className="rounded-full">
              <Heart className="h-5 w-5" />
            </Button>
          </Link>
          {isAuthed ? (
            <Link to="/painel/notificacoes" aria-label="Notificações" className="relative hidden sm:inline-flex">
              <Button variant="ghost" size="icon" className="rounded-full">
                <Bell className="h-5 w-5" />
                {unreadCount > 0 && (
                  <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-destructive-foreground">
                    {unreadCount > 99 ? "99+" : unreadCount}
                  </span>
                )}
              </Button>
            </Link>
          ) : null}

          {isAuthed ? (
            <Button
              variant="ghost"
              size="sm"
              className="hidden lg:inline-flex gap-1"
              onClick={handleSignOut}
              aria-label={site.header.logout_label}
            >
              <LogOut className="h-4 w-4" /> {site.header.logout_label}
            </Button>
          ) : (
            <Link to="/auth">
              <Button variant="ghost" size="sm">
                {site.header.login_label}
              </Button>
            </Link>
          )}

          <Link to="/planos" className="hidden sm:inline-flex">
            <Button
              size="sm"
              className="btn-shine rounded-full bg-accent px-3 text-accent-foreground shadow-sm hover:bg-accent/90 sm:px-4"
            >
              Anunciar
            </Button>
          </Link>
        </div>
      </div>

      {!hideMobileSearch ? (
        <div className="border-t border-border/60 bg-background px-3 py-2 lg:hidden">
          <HeaderSearch />
        </div>
      ) : null}

      {open ? (
        <div className="lg:hidden border-t border-border bg-background/95 backdrop-blur-xl animate-fade-up">
          <nav className="container mx-auto flex flex-col gap-1 px-4 py-3">
            <div className="mb-2 md:hidden">
              <CityPickerDialog />
            </div>
            {NAV.map((n) => {
              const active = isActive(n.to);
              const Icon = NAV_ICONS[n.to];
              const label = stripLeadingEmoji(n.label);
              return (
                <Link
                  key={n.to}
                  to={n.to as any}
                  search={n.to === "/servicos-publicos" ? ({} as any) : undefined}
                  onClick={() => setOpen(false)}
                  className={[
                    "flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors",
                    n.danger
                      ? "text-destructive hover:bg-destructive/10"
                      : active
                        ? "bg-primary/10 text-primary"
                        : "text-foreground/80 hover:bg-primary/5 hover:text-primary",
                  ].join(" ")}
                >
                  {Icon ? (
                    <span
                      className={[
                        "flex h-8 w-8 items-center justify-center rounded-lg",
                        n.danger
                          ? "bg-destructive/10 text-destructive"
                          : active
                            ? "bg-primary text-primary-foreground"
                            : "bg-primary/10 text-primary",
                      ].join(" ")}
                      aria-hidden
                    >
                      <Icon className="h-4 w-4" strokeWidth={2.2} />
                    </span>
                  ) : null}
                  <span>{label}</span>
                </Link>
              );
            })}

            <div className="mt-2 border-t border-border pt-3">
              <p className="px-3 pb-2 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Categorias</p>
              <div className="grid grid-cols-2 gap-1">
                {(cats.data ?? []).slice(0, 12).map((c) => (
                  <Link
                    key={c.id}
                    to="/categoria/$slug"
                    params={{ slug: c.slug }}
                    onClick={() => setOpen(false)}
                    className="rounded-lg px-3 py-2 text-sm hover:bg-muted"
                  >
                    {c.name}
                  </Link>
                ))}
              </div>
            </div>

            {isAuthed || isAdmin ? (
              <div className="mt-2 grid grid-cols-2 gap-2 border-t border-border pt-3">
                {isAuthed ? (
                  <Link to="/painel" className="col-span-1" onClick={() => setOpen(false)}>
                    <Button variant="outline" size="sm" className="w-full gap-2">
                      <LayoutDashboard className="h-4 w-4" /> Meu painel
                    </Button>
                  </Link>
                ) : null}
                {isAdmin ? (
                  <Link to="/admin" className="col-span-1" onClick={() => setOpen(false)}>
                    <Button variant="outline" size="sm" className="w-full gap-2">
                      <ShieldCheck className="h-4 w-4" /> Admin
                    </Button>
                  </Link>
                ) : null}
              </div>
            ) : null}
            <div className="mt-2 flex gap-2 border-t border-border pt-3">
              <Link to="/favoritos" className="flex-1" onClick={() => setOpen(false)}>
                <Button variant="outline" size="sm" className="w-full gap-2">
                  <Heart className="h-4 w-4" /> Favoritos
                </Button>
              </Link>
              <Link to="/planos" className="flex-1" onClick={() => setOpen(false)}>
                <Button size="sm" className="w-full bg-accent text-accent-foreground">Anunciar</Button>
              </Link>
            </div>
            {isAuthed ? (
              <Button variant="outline" size="sm" className="mt-2 w-full gap-2" onClick={handleSignOut}>
                <LogOut className="h-4 w-4" /> Sair
              </Button>
            ) : null}
          </nav>
        </div>
      ) : null}
    </header>
  );
}
