import { useEffect, useState } from "react";
import { supabase } from "@/integrations/supabase/client";
import { checkIsAdmin } from "@/lib/admin";

export function useAdmin() {
  const [loading, setLoading] = useState(true);
  const [isAdmin, setIsAdmin] = useState(false);
  const [userId, setUserId] = useState<string | null>(null);

  useEffect(() => {
    let mounted = true;

    const evaluate = (uid: string | null) => {
      if (!mounted) return;
      setUserId(uid);
      if (!uid) {
        setIsAdmin(false);
        setLoading(false);
        return;
      }
      // fire-and-forget to avoid blocking auth callback
      checkIsAdmin(uid)
        .then((ok) => {
          if (!mounted) return;
          setIsAdmin(ok);
        })
        .catch(() => {
          if (!mounted) return;
          setIsAdmin(false);
        })
        .finally(() => {
          if (!mounted) return;
          setLoading(false);
        });
    };

    // Subscribe FIRST so we don't miss the INITIAL_SESSION event
    const { data: sub } = supabase.auth.onAuthStateChange((_event, session) => {
      evaluate(session?.user?.id ?? null);
    });

    // Then restore session from storage
    supabase.auth.getSession().then(({ data }) => {
      evaluate(data.session?.user?.id ?? null);
    });

    return () => {
      mounted = false;
      sub.subscription.unsubscribe();
    };
  }, []);

  return { loading, isAdmin, userId };
}
