import fs from "node:fs";
import path from "node:path";
import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import tsconfigPaths from "vite-tsconfig-paths";
import { tanstackRouter } from "@tanstack/router-plugin/vite";

function excludeOldInstaller() {
  return {
    name: "exclude-old-installer",
    closeBundle() {
      for (const name of ["instalar.php", "atualizar-banco.php", "instalar-banco.php"]) {
        const file = path.resolve("dist", name);
        if (fs.existsSync(file)) {
          fs.unlinkSync(file);
        }
      }
    },
  };
}

export default defineConfig({
  plugins: [
    tanstackRouter({
      target: "react",
      autoCodeSplitting: true,
    }),
    react(),
    tailwindcss(),
    tsconfigPaths(),
    excludeOldInstaller(),
  ],
  server: {
    proxy: {
      "/api": {
        target: process.env.VITE_PHP_PROXY ?? "http://127.0.0.1:8098",
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, "") || "/",
      },
    },
  },
});
