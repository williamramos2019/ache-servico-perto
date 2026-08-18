import js from "@eslint/js";
import eslintPluginPrettier from "eslint-plugin-prettier/recommended";
import globals from "globals";
import reactHooks from "eslint-plugin-react-hooks";
import reactRefresh from "eslint-plugin-react-refresh";
import tseslint from "typescript-eslint";

export default tseslint.config(
  { ignores: ["dist", ".output", ".vinxi", "src/routeTree.gen.ts"] },
  {
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    files: ["**/*.{ts,tsx}"],
    languageOptions: {
      ecmaVersion: 2020,
      globals: globals.browser,
    },
    plugins: {
      "react-hooks": reactHooks,
      "react-refresh": reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      "no-restricted-imports": [
        "error",
        {
          paths: [
            {
              name: "server-only",
              message: "This SPA has no Node/Nitro runtime. Keep data access in the PHP API.",
            },
            {
              name: "@supabase/supabase-js",
              message: "Supabase is not the production backend. Use src/lib/php-api.ts.",
            },
            {
              name: "@lovable.dev/cloud-auth-js",
              message: "Lovable Cloud Auth is not used in production. Use src/lib/php-auth.ts.",
            },
            {
              name: "web-push",
              message: "web-push is Node-only and must not enter the SPA bundle.",
            },
          ],
          patterns: [
            {
              group: ["**/integrations/supabase/**", "**/integrations/lovable/**", "**/legacy-server/**"],
              message: "Legacy Node/Supabase paths were removed for HostGator PHP production.",
            },
          ],
        },
      ],
      "react-refresh/only-export-components": ["warn", { allowConstantExport: true }],
      "@typescript-eslint/no-unused-vars": "off",
    },
  },
  eslintPluginPrettier,
);
