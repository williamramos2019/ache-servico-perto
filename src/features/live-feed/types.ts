import type { LiveFeedItem } from "@/lib/domain-api";

export type LiveFeedKind =
  | "evento"
  | "vaga"
  | "edital"
  | "promocao"
  | "atividade"
  | "marketplace";

export const KIND_META: Record<string, { icon: string; label: string }> = {
  evento: { icon: "🎉", label: "Evento" },
  vaga: { icon: "💼", label: "Vaga" },
  edital: { icon: "📄", label: "Edital" },
  promocao: { icon: "🏷️", label: "Promoção" },
  atividade: { icon: "🏛️", label: "Vereador" },
  marketplace: { icon: "🛒", label: "Marketplace" },
};

export const KIND_FILTERS: Array<{ value: LiveFeedKind | ""; label: string }> = [
  { value: "", label: "Tudo" },
  { value: "evento", label: "Eventos" },
  { value: "vaga", label: "Vagas" },
  { value: "edital", label: "Editais" },
  { value: "promocao", label: "Promoções" },
  { value: "atividade", label: "Vereadores" },
  { value: "marketplace", label: "Marketplace" },
];

export type { LiveFeedItem };
