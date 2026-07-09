import { useQuery } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";

export type SiteContent = {
  brand: { name: string; tagline: string };
  header: {
    cta_label: string;
    panel_label: string;
    admin_label: string;
    login_label: string;
    logout_label: string;
  };
  footer: {
    about_text: string;
    nav_title: string;
    biz_title: string;
    copyright: string;
    location: string;
  };
  newsletter: {
    title: string;
    description: string;
    email_placeholder: string;
    name_placeholder: string;
    button_label: string;
  };
  home: {
    hero_overline: string;
    hero_title: string;
    hero_subtitle: string;
    cta_title: string;
    cta_subtitle: string;
    cta_button: string;
  };
  about: {
    title: string;
    subtitle: string;
    p1: string;
    p2: string;
    p3: string;
  };
  contact: {
    title: string;
    subtitle: string;
    email: string;
    whatsapp_url: string;
    whatsapp_label: string;
  };
};

export const DEFAULT_SITE_CONTENT: SiteContent = {
  brand: { name: "AgendaAqui", tagline: "Vespasiano · S. J. da Lapa" },
  header: {
    cta_label: "Anunciar",
    panel_label: "Painel",
    admin_label: "Admin",
    login_label: "Entrar",
    logout_label: "Sair",
  },
  footer: {
    about_text:
      "Tudo sobre sua cidade num só app. Serviços públicos, emergência e o guia de empresas de Vespasiano e São José da Lapa.",
    nav_title: "Navegação",
    biz_title: "Para empresas",
    copyright: "AgendaAqui. Todos os direitos reservados.",
    location: "Minas Gerais, Brasil",
  },
  newsletter: {
    title: "Newsletter",
    description: "Receba dicas e novidades sobre serviços em MG.",
    email_placeholder: "seu@email.com",
    name_placeholder: "Seu nome (opcional)",
    button_label: "Assinar",
  },
  home: {
    hero_overline: "App da Cidade",
    hero_title: "Tudo sobre sua cidade num só app",
    hero_subtitle:
      "Serviços públicos, telefones de emergência e o guia de empresas locais de Vespasiano e São José da Lapa.",
    cta_title: "Tem uma empresa na cidade?",
    cta_subtitle:
      "Cadastre-se grátis e apareça para quem mora em Vespasiano e São José da Lapa.",
    cta_button: "Anunciar grátis",
  },
  about: {
    title: "Sobre o AgendaAqui",
    subtitle: "Seu serviço certo, na hora certa.",
    p1: "O AgendaAqui nasceu para conectar pessoas a empresas e profissionais de confiança em Vespasiano e São José da Lapa.",
    p2: "Aqui você encontra prestadores de serviços de construção civil, higienização, eventos e muito mais.",
    p3: "É grátis para usuários e grátis para começar a anunciar. Cadastre sua empresa em minutos.",
  },
  contact: {
    title: "Fale com a gente",
    subtitle: "Sugestões, parcerias ou dúvidas? Estamos por aqui.",
    email: "contato@agendaaqui.online",
    whatsapp_url: "https://wa.me/5531999999999",
    whatsapp_label: "Atendimento em horário comercial",
  },
};

function deepMerge<T>(base: T, override: unknown): T {
  if (!override || typeof override !== "object") return base;
  const out: Record<string, unknown> = { ...(base as Record<string, unknown>) };
  for (const [k, v] of Object.entries(override as Record<string, unknown>)) {
    const b = out[k];
    if (b && typeof b === "object" && !Array.isArray(b) && v && typeof v === "object" && !Array.isArray(v)) {
      out[k] = deepMerge(b, v);
    } else if (v !== undefined && v !== null && v !== "") {
      out[k] = v;
    }
  }
  return out as T;
}

export async function fetchSiteContent(): Promise<SiteContent> {
  const { data, error } = await supabase
    .from("system_settings")
    .select("value")
    .eq("key", "site_content")
    .maybeSingle();
  if (error || !data?.value) return DEFAULT_SITE_CONTENT;
  return deepMerge(DEFAULT_SITE_CONTENT, data.value);
}

export async function saveSiteContent(content: SiteContent): Promise<void> {
  const { error } = await supabase.from("system_settings").upsert(
    {
      key: "site_content",
      value: content as never,
      is_public: true,
      updated_at: new Date().toISOString(),
    },
    { onConflict: "key" },
  );
  if (error) throw error;
}

export function useSiteContent(): SiteContent {
  const { data } = useQuery({
    queryKey: ["site-content"],
    queryFn: fetchSiteContent,
    staleTime: 5 * 60_000,
    placeholderData: DEFAULT_SITE_CONTENT,
  });
  return data ?? DEFAULT_SITE_CONTENT;
}
