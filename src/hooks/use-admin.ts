import { useEffect, useState } from "react";
import { fetchCurrentUser, onPhpAuthChange } from "@/lib/php-auth";

export function useAdmin() {
  const [loading, setLoading] = useState(true);
  const [isAdmin, setIsAdmin] = useState(false);
  const [userId, setUserId] = useState<string | null>(null);

  useEffect(() => {
    let mounted = true;

    const evaluate = async () => {
      try {
        const user = await fetchCurrentUser();
        if (!mounted) return;
        setUserId(user?.id ?? null);
        setIsAdmin(Boolean(user?.roles?.includes("admin")));
      } catch {
        if (!mounted) return;
        setUserId(null);
        setIsAdmin(false);
      } finally {
        if (mounted) setLoading(false);
      }
    };

    evaluate();
    const unsubscribe = onPhpAuthChange(() => {
      void evaluate();
    });

    return () => {
      mounted = false;
      unsubscribe();
    };
  }, []);

  return { loading, isAdmin, userId };
}
