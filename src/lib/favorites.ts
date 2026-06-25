import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { supabase } from "@/integrations/supabase/client";

export function useCurrentUserId() {
  const [userId, setUserId] = useState<string | null>(null);
  useEffect(() => {
    supabase.auth.getUser().then(({ data }) => setUserId(data.user?.id ?? null));
    const { data: sub } = supabase.auth.onAuthStateChange((_e, session) => {
      setUserId(session?.user?.id ?? null);
    });
    return () => sub.subscription.unsubscribe();
  }, []);
  return userId;
}

export function useFavorites() {
  const userId = useCurrentUserId();
  return useQuery({
    queryKey: ["favorites", userId],
    enabled: !!userId,
    queryFn: async () => {
      const { data, error } = await supabase
        .from("favorites")
        .select("company_id, created_at, companies(id, slug, name, tagline, banner_url, logo_url, plan, featured, cities(name, slug))")
        .order("created_at", { ascending: false });
      if (error) throw error;
      return data ?? [];
    },
  });
}

export function useToggleFavorite(companyId: string) {
  const qc = useQueryClient();
  const userId = useCurrentUserId();

  const isFav = useQuery({
    queryKey: ["fav", userId, companyId],
    enabled: !!userId,
    queryFn: async () => {
      const { data } = await supabase
        .from("favorites")
        .select("company_id")
        .eq("user_id", userId!)
        .eq("company_id", companyId)
        .maybeSingle();
      return !!data;
    },
  });

  const mutation = useMutation({
    mutationFn: async () => {
      if (!userId) throw new Error("auth");
      if (isFav.data) {
        const { error } = await supabase.from("favorites").delete().eq("user_id", userId).eq("company_id", companyId);
        if (error) throw error;
        return false;
      }
      const { error } = await supabase.from("favorites").insert({ user_id: userId, company_id: companyId });
      if (error) throw error;
      return true;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["fav", userId, companyId] });
      qc.invalidateQueries({ queryKey: ["favorites", userId] });
    },
  });

  return { isFav: !!isFav.data, isLoggedIn: !!userId, toggle: mutation.mutate, isPending: mutation.isPending };
}
