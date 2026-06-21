import {
  HardHat, Sparkles, Truck, Wrench, PaintRoller, Zap, Droplets,
  Building2, UtensilsCrossed, HeartPulse, Laptop, Printer, Tag,
} from "lucide-react";

const map: Record<string, React.ComponentType<{ className?: string }>> = {
  HardHat, Sparkles, Truck, Wrench, PaintRoller, Zap, Droplets,
  Building2, UtensilsCrossed, HeartPulse, Laptop, Printer,
};

export function CategoryIcon({ name, className }: { name?: string | null; className?: string }) {
  const Icon = (name && map[name]) || Tag;
  return <Icon className={className} />;
}
