CREATE TABLE public.company_claims (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  company_id uuid NOT NULL REFERENCES public.companies(id) ON DELETE CASCADE,
  user_id uuid NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  full_name text NOT NULL,
  role_in_company text,
  phone text NOT NULL,
  email text NOT NULL,
  document text,
  message text,
  proof_url text,
  status text NOT NULL DEFAULT 'pending',
  admin_notes text,
  reviewed_by uuid,
  reviewed_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX company_claims_one_pending ON public.company_claims (company_id, user_id) WHERE status = 'pending';
CREATE INDEX company_claims_status_idx ON public.company_claims (status, created_at DESC);

GRANT SELECT, INSERT ON public.company_claims TO authenticated;
GRANT UPDATE, DELETE ON public.company_claims TO authenticated;
GRANT ALL ON public.company_claims TO service_role;

ALTER TABLE public.company_claims ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can create their own claims"
ON public.company_claims FOR INSERT TO authenticated
WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can view their own claims"
ON public.company_claims FOR SELECT TO authenticated
USING (auth.uid() = user_id OR public.has_role(auth.uid(), 'admin'));

CREATE POLICY "Admins can update claims"
ON public.company_claims FOR UPDATE TO authenticated
USING (public.has_role(auth.uid(), 'admin'))
WITH CHECK (public.has_role(auth.uid(), 'admin'));

CREATE POLICY "Admins can delete claims"
ON public.company_claims FOR DELETE TO authenticated
USING (public.has_role(auth.uid(), 'admin'));

CREATE TRIGGER company_claims_set_updated_at
BEFORE UPDATE ON public.company_claims
FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE OR REPLACE FUNCTION public.approve_company_claim(_claim_id uuid, _notes text DEFAULT NULL)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  _claim public.company_claims%ROWTYPE;
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin') THEN
    RAISE EXCEPTION 'Apenas administradores podem aprovar reivindicações' USING ERRCODE = '42501';
  END IF;

  SELECT * INTO _claim FROM public.company_claims WHERE id = _claim_id;
  IF NOT FOUND THEN
    RAISE EXCEPTION 'Reivindicação não encontrada';
  END IF;

  UPDATE public.companies
  SET owner_id = _claim.user_id
  WHERE id = _claim.company_id;

  UPDATE public.company_claims
  SET status = 'approved', admin_notes = COALESCE(_notes, admin_notes),
      reviewed_by = auth.uid(), reviewed_at = now()
  WHERE id = _claim_id;

  UPDATE public.company_claims
  SET status = 'rejected', reviewed_by = auth.uid(), reviewed_at = now(),
      admin_notes = COALESCE(admin_notes, 'Empresa reivindicada por outro usuário')
  WHERE company_id = _claim.company_id AND id <> _claim_id AND status = 'pending';

  INSERT INTO public.user_roles (user_id, role)
  VALUES (_claim.user_id, 'company_owner'::app_role)
  ON CONFLICT (user_id, role) DO NOTHING;

  INSERT INTO public.notifications (user_id, type, title, body, payload)
  VALUES (_claim.user_id, 'claim_approved', 'Reivindicação aprovada',
          'Você agora é o responsável por esta empresa. Gerencie tudo pelo seu painel.',
          jsonb_build_object('company_id', _claim.company_id));
END;
$$;

CREATE OR REPLACE FUNCTION public.reject_company_claim(_claim_id uuid, _notes text DEFAULT NULL)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
  _claim public.company_claims%ROWTYPE;
BEGIN
  IF NOT public.has_role(auth.uid(), 'admin') THEN
    RAISE EXCEPTION 'Apenas administradores podem recusar reivindicações' USING ERRCODE = '42501';
  END IF;

  SELECT * INTO _claim FROM public.company_claims WHERE id = _claim_id;
  IF NOT FOUND THEN
    RAISE EXCEPTION 'Reivindicação não encontrada';
  END IF;

  UPDATE public.company_claims
  SET status = 'rejected', admin_notes = COALESCE(_notes, admin_notes),
      reviewed_by = auth.uid(), reviewed_at = now()
  WHERE id = _claim_id;

  INSERT INTO public.notifications (user_id, type, title, body, payload)
  VALUES (_claim.user_id, 'claim_rejected', 'Reivindicação recusada',
          COALESCE(_notes, 'Não conseguimos confirmar o vínculo com esta empresa.'),
          jsonb_build_object('company_id', _claim.company_id));
END;
$$;