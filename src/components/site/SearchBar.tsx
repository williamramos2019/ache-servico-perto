import { useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import { Search, MapPin } from "lucide-react";
import { Button } from "@/components/ui/button";

const CITIES = [
  { slug: "todas", name: "Todas as cidades" },
  { slug: "vespasiano", name: "Vespasiano" },
  { slug: "sao-jose-da-lapa", name: "São José da Lapa" },
  { slug: "lagoa-santa", name: "Lagoa Santa" },
  { slug: "belo-horizonte", name: "Belo Horizonte" },
];

export function SearchBar({ defaultQ = "", defaultCity = "todas" }: { defaultQ?: string; defaultCity?: string }) {
  const navigate = useNavigate();
  const [q, setQ] = useState(defaultQ);
  const [city, setCity] = useState(defaultCity);

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        navigate({ to: "/buscar", search: { q: q || undefined, city: city !== "todas" ? city : undefined } });
      }}
      className="flex w-full flex-col gap-2 rounded-2xl border border-border bg-card p-2 shadow-xl md:flex-row md:items-center md:gap-0 md:rounded-full md:p-1.5"
    >
      <div className="flex flex-1 items-center gap-2 px-3 py-2">
        <Search className="h-5 w-5 text-muted-foreground" />
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="O que você procura? Ex: eletricista, gráfica..."
          className="w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
        />
      </div>
      <div className="hidden h-8 w-px bg-border md:block" />
      <div className="flex items-center gap-2 px-3 py-2 md:min-w-[220px]">
        <MapPin className="h-5 w-5 text-muted-foreground" />
        <select
          value={city}
          onChange={(e) => setCity(e.target.value)}
          className="w-full bg-transparent text-sm outline-none"
        >
          {CITIES.map((c) => (
            <option key={c.slug} value={c.slug}>{c.name}</option>
          ))}
        </select>
      </div>
      <Button type="submit" size="lg" className="rounded-xl md:rounded-full">
        Buscar agora
      </Button>
    </form>
  );
}
