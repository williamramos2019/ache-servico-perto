import { phpGet, phpPost, PhpApiError } from "@/lib/php-api";

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

export async function createClaim(_userId: string, input: ClaimInput) {
  try {
    return await phpPost<{ id: string; status: string }>("/api/claims/index.php", {
      op: "create",
      ...input,
    });
  } catch (error) {
    if (error instanceof PhpApiError && (error.status === 409 || error.code === "already_pending")) {
      throw new Error("Você já tem uma solicitação pendente para esta empresa.");
    }
    throw error;
  }
}

export async function getMyClaimForCompany(_userId: string, companyId: string) {
  const data = await phpGet<{
    claim: { id: string; status: string; created_at: string; admin_notes: string | null } | null;
  }>(`/api/claims/index.php?op=for_company&company_id=${encodeURIComponent(companyId)}`);
  return data.claim;
}

export async function listMyClaims(_userId: string) {
  const data = await phpGet<{
    claims: Array<{
      id: string;
      status: string;
      created_at: string;
      admin_notes: string | null;
      company_id: string;
      companies: { name: string; slug: string };
    }>;
  }>("/api/claims/index.php?op=mine");
  return data.claims ?? [];
}

export type AdminClaim = {
  id: string;
  status: string;
  created_at: string;
  reviewed_at: string | null;
  admin_notes: string | null;
  full_name: string;
  role_in_company: string | null;
  phone: string;
  email: string;
  document: string | null;
  message: string | null;
  proof_url: string | null;
  user_id: string;
  company_id: string;
  companies: {
    id: string;
    name: string;
    slug: string;
    owner_id: string | null;
    email: string | null;
    phone: string | null;
  };
};

export async function adminListClaims(status: "all" | ClaimStatus = "pending") {
  const data = await phpGet<{ claims: AdminClaim[] }>(
    `/api/claims/index.php?status=${encodeURIComponent(status)}`,
  );
  return data.claims ?? [];
}

export async function adminApproveClaim(claimId: string, notes?: string) {
  await phpPost("/api/claims/index.php", { op: "approve", id: claimId, notes: notes ?? null });
}

export async function adminRejectClaim(claimId: string, notes?: string) {
  await phpPost("/api/claims/index.php", { op: "reject", id: claimId, notes: notes ?? null });
}
