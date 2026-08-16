import { supabase } from "@/integrations/supabase/client";

export type ClaimStatus = "pending" | "approved" | "rejected";

export type ClaimInput = {
  company_id: string;
  full_name: string;
  role_in_company?: string | null;
  phone: string;
  email: string;
  document?: string | null;
  message?: string | null;
  proof_url?: string | null;
};

export async function createClaim(userId: string, input: ClaimInput) {
  const { data, error } = await supabase
    .from("company_claims")
    .insert({
      user_id: userId,
      company_id: input.company_id,
      full_name: input.full_name,
      role_in_company: input.role_in_company || null,
      phone: input.phone,
      email: input.email,
      document: input.document || null,
      message: input.message || null,
      proof_url: input.proof_url || null,
    })
    .select("id, status")
    .single();
  if (error) {
    if ((error as { code?: string }).code === "23505") {
      throw new Error("Você já tem uma solicitação pendente para esta empresa.");
    }
    throw error;
  }
  return data;
}

export async function getMyClaimForCompany(userId: string, companyId: string) {
  const { data, error } = await supabase
    .from("company_claims")
    .select("id, status, created_at, admin_notes")
    .eq("user_id", userId)
    .eq("company_id", companyId)
    .order("created_at", { ascending: false })
    .limit(1)
    .maybeSingle();
  if (error) throw error;
  return data;
}

export async function listMyClaims(userId: string) {
  const { data, error } = await supabase
    .from("company_claims")
    .select("id, status, created_at, admin_notes, company_id, companies(name, slug)")
    .eq("user_id", userId)
    .order("created_at", { ascending: false });
  if (error) throw error;
  return data ?? [];
}

export async function adminListClaims(status: "all" | ClaimStatus = "pending") {
  let query = supabase
    .from("company_claims")
    .select(
      "id, status, created_at, reviewed_at, admin_notes, full_name, role_in_company, phone, email, document, message, proof_url, user_id, company_id, companies(id, name, slug, owner_id, email, phone)",
    )
    .order("created_at", { ascending: false })
    .limit(300);
  if (status !== "all") query = query.eq("status", status);
  const { data, error } = await query;
  if (error) throw error;
  return data ?? [];
}

export async function adminApproveClaim(claimId: string, notes?: string) {
  const { error } = await supabase.rpc("approve_company_claim", { _claim_id: claimId, _notes: notes ?? undefined });
  if (error) throw error;
}

export async function adminRejectClaim(claimId: string, notes?: string) {
  const { error } = await supabase.rpc("reject_company_claim", { _claim_id: claimId, _notes: notes ?? undefined });
  if (error) throw error;
}
