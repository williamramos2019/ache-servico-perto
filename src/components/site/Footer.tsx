import { Link } from "@tanstack/react-router";
import { MapPin } from "lucide-react";
import { NewsletterForm } from "@/components/site/NewsletterForm";

export function Footer() {
  return (
    <footer className="mt-16 border-t border-border bg-surface">
      <div className="container mx-auto grid gap-10 px-4 py-12 md:grid-cols-4">
        <div>
          <div className="flex items-center gap-2">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
              <MapPin className="h-5 w-5" />
            </div>
            <div className="font-display text-lg font-extrabold">AgendaAqui</div>
          </div>
          <p className="mt-3 text-sm text-muted-foreground">
            Seu serviço certo, na hora certa. Encontre empresas e profissionais verificados perto de você.
          </p>
        </div>
        <div>
          <h4 className="text-sm font-semibold">Navegação</h4>
          <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
            <li><Link to="/" className="hover:text-foreground">Início</Link></li>
            <li><Link to="/buscar" className="hover:text-foreground">Buscar serviços</Link></li>
            <li><Link to="/blog" className="hover:text-foreground">Blog</Link></li>
            <li><Link to="/favoritos" className="hover:text-foreground">Favoritos</Link></li>
            <li><Link to="/sobre" className="hover:text-foreground">Sobre</Link></li>
            <li><Link to="/contato" className="hover:text-foreground">Contato</Link></li>
          </ul>
        </div>
        <div>
          <h4 className="text-sm font-semibold">Para empresas</h4>
          <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
            <li><Link to="/planos" className="hover:text-foreground">Planos e preços</Link></li>
            <li><Link to="/auth" className="hover:text-foreground">Anunciar grátis</Link></li>
            <li><Link to="/auth" className="hover:text-foreground">Entrar</Link></li>
          </ul>
        </div>
        <div>
          <h4 className="text-sm font-semibold">Newsletter</h4>
          <p className="mt-3 text-sm text-muted-foreground">
            Receba dicas e novidades sobre serviços em MG.
          </p>
          <div className="mt-3"><NewsletterForm compact /></div>
        </div>
      </div>
      <div className="border-t border-border">
        <div className="container mx-auto flex flex-col items-center justify-between gap-2 px-4 py-4 text-xs text-muted-foreground md:flex-row">
          <p>© {new Date().getFullYear()} AgendaAqui. Todos os direitos reservados.</p>
          <p>Minas Gerais, Brasil</p>
        </div>
      </div>
    </footer>
  );
}
