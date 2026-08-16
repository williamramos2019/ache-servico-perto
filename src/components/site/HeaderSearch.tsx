import { useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import { Search } from "lucide-react";
import { useSelectedCity, CITY_OPTIONS } from "@/hooks/useSelectedCity";

export function HeaderSearch({ className = "" }: { className?: string }) {
  const navigate = useNavigate();
  const { city } = useSelectedCity();
  const [q, setQ] = useState("");
  const cityName = CITY_OPTIONS.find((c) => c.slug === city)?.name ?? "sua cidade";

  return (
    <form
      role="search"
      onSubmit={(e) => {
        e.preventDefault();
        navigate({
          to: "/buscar",
          search: { q: q.trim() || undefined, city },
        });
      }}
      className={`flex min-w-0 flex-1 items-center gap-2 rounded-full border border-border bg-muted/60 px-3 py-1.5 focus-within:border-primary focus-within:bg-background ${className}`}
    >
      <Search className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />
      <input
        value={q}
        onChange={(e) => setQ(e.target.value)}
        placeholder={`O que você precisa em ${cityName}?`}
        aria-label={`Buscar empresas e serviços em ${cityName}`}
        className="w-full min-w-0 bg-transparent text-sm text-foreground outline-none placeholder:text-muted-foreground"
      />
    </form>
  );
}
