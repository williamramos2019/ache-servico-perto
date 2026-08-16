import { useState } from "react";
import { Link } from "@tanstack/react-router";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { BadgeCheck, ShieldCheck, Clock, XCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from "@/components/ui/dialog";
import { useAdmin } from "@/hooks/use-admin";
import { createClaim, getMyClaimForCompany } from "@/lib/claims";

type Props = { companyId: string; companyName: string };

export function ClaimCompanyDialog({ companyId, companyName }: Props) {
  const { userId, loading } = useAdmin();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    full_name: "", role_in_company: "", phone: "", email: "", document: "", proof_url: "", message: "",
  });

  const claim = useQuery({
    queryKey: ["my-claim", userId, companyId],
    queryFn: () => getMyClaimForCompany(userId!, companyId),
    enabled: !!userId,
  });

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit() {
    if (!userId) return;
    if (!form.full_name.trim() || !form.phone.trim() || !form.email.trim()) {
      toast.error("Preencha nome, telefone e e-mail.");
      return;
    }
    setSaving(true);
    try {
      await createClaim(userId, { company_id: companyId, ...form });
      toast.success("Solicitação enviada! Nossa equipe vai analisar e entrar em contato.");
      setOpen(false);
      qc.invalidateQueries({ queryKey: ["my-claim", userId, companyId] });
      qc.invalidateQueries({ queryKey: ["my-claims"] });
    } catch (e) {
      toast.error((e as Error).message);
    } finally {
      setSaving(false);
    }
  }

  if (loading) return null;

  const existing = claim.data;
  if (existing?.status === "pending") {
    return (
      <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
        <Clock className="h-4 w-4 shrink-0" /> Sua reivindicação está em análise.
      </div>
    );
  }
  if (existing?.status === "rejected") {
    return (
      <div className="flex items-start gap-2 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
        <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-destructive" />
        <span>Reivindicação recusada{existing.admin_notes ? `: ${existing.admin_notes}` : "."}</span>
      </div>
    );
  }

  if (!userId) {
    return (
      <Link to="/auth" className="block">
        <Button variant="outline" className="w-full gap-2">
          <BadgeCheck className="h-4 w-4" /> É a sua empresa? Reivindicar
        </Button>
      </Link>
    );
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="outline" className="w-full gap-2">
          <BadgeCheck className="h-4 w-4" /> É a sua empresa? Reivindicar
        </Button>
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Reivindicar {companyName}</DialogTitle>
          <DialogDescription>
            Confirme que você é o responsável por esta empresa. Nossa equipe verifica os dados antes de liberar o acesso.
          </DialogDescription>
        </DialogHeader>

        <div className="grid gap-3">
          <div className="grid gap-1.5">
            <Label htmlFor="claim-name">Seu nome completo *</Label>
            <Input id="claim-name" value={form.full_name} onChange={set("full_name")} placeholder="Maria Silva" />
          </div>
          <div className="grid gap-1.5 sm:grid-cols-2 sm:gap-3">
            <div className="grid gap-1.5">
              <Label htmlFor="claim-role">Cargo na empresa</Label>
              <Input id="claim-role" value={form.role_in_company} onChange={set("role_in_company")} placeholder="Proprietário(a)" />
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="claim-doc">CNPJ ou CPF</Label>
              <Input id="claim-doc" value={form.document} onChange={set("document")} placeholder="00.000.000/0001-00" />
            </div>
          </div>
          <div className="grid gap-1.5 sm:grid-cols-2 sm:gap-3">
            <div className="grid gap-1.5">
              <Label htmlFor="claim-phone">Telefone / WhatsApp *</Label>
              <Input id="claim-phone" value={form.phone} onChange={set("phone")} placeholder="(31) 90000-0000" />
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="claim-email">E-mail *</Label>
              <Input id="claim-email" type="email" value={form.email} onChange={set("email")} placeholder="voce@empresa.com" />
            </div>
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="claim-proof">Link de comprovação (site, rede social, nota fiscal)</Label>
            <Input id="claim-proof" value={form.proof_url} onChange={set("proof_url")} placeholder="https://" />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="claim-msg">Mensagem</Label>
            <Textarea id="claim-msg" rows={3} value={form.message} onChange={set("message")} placeholder="Conte como podemos confirmar seu vínculo com a empresa." />
          </div>
          <p className="flex items-start gap-2 text-xs text-muted-foreground">
            <ShieldCheck className="mt-0.5 h-3.5 w-3.5 shrink-0" />
            Seus dados são usados apenas para verificação e não aparecem no perfil público.
          </p>
        </div>

        <DialogFooter>
          <Button variant="ghost" onClick={() => setOpen(false)}>Cancelar</Button>
          <Button onClick={submit} disabled={saving}>{saving ? "Enviando…" : "Enviar solicitação"}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
