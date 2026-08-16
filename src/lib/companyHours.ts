const WEEK_ORDER = ["seg", "ter", "qua", "qui", "sex", "sab", "dom"];
const JS_DAY_TO_KEY = ["dom", "seg", "ter", "qua", "qui", "sex", "sab"];

function expandKey(key: string): string[] {
  if (!key.includes("-")) return [key];
  const [a, b] = key.split("-");
  const start = WEEK_ORDER.indexOf(a);
  const end = WEEK_ORDER.indexOf(b);
  if (start < 0 || end < 0 || start > end) return [key];
  return WEEK_ORDER.slice(start, end + 1);
}

export function companyOpenStatus(hours: Record<string, string> | null | undefined): {
  open: boolean | null;
  label: string;
} {
  if (!hours || Object.keys(hours).length === 0) {
    return { open: null, label: "Horário não informado" };
  }
  const now = new Date();
  const todayKey = JS_DAY_TO_KEY[now.getDay()];
  const minutes = now.getHours() * 60 + now.getMinutes();
  const intervals: Array<[number, number]> = [];
  let hasParseable = false;

  for (const [key, value] of Object.entries(hours)) {
    const coversToday = expandKey(key).includes(todayKey);
    for (const part of value.split(",").map((s) => s.trim()).filter(Boolean)) {
      const times = part.match(/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/);
      if (!times) continue;
      hasParseable = true;
      if (!coversToday) continue;
      intervals.push([
        parseInt(times[1], 10) * 60 + parseInt(times[2], 10),
        parseInt(times[3], 10) * 60 + parseInt(times[4], 10),
      ]);
    }
  }

  if (!hasParseable) return { open: null, label: "Horário não informado" };
  if (intervals.length === 0) return { open: false, label: "Fechado hoje" };

  const open = intervals.some(([start, end]) => minutes >= start && minutes <= end);
  const next = intervals[0];
  const fmt = (m: number) => `${String(Math.floor(m / 60)).padStart(2, "0")}:${String(m % 60).padStart(2, "0")}`;
  if (open) {
    const closing = Math.max(...intervals.map(([, end]) => end));
    return { open: true, label: `Aberto agora · fecha às ${fmt(closing)}` };
  }
  return { open: false, label: `Fechado · abre às ${fmt(next[0])}` };
}
