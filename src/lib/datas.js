const ISO = /^(\d{4})-(\d{2})-(\d{2})$/;
const BR = /^(\d{2})\/(\d{2})\/(\d{4})$/;

/** "01/01/2026" -> "2026-01-01". Retorna null se não casar o formato. */
export function brParaIso(data) {
  const m = BR.exec(String(data).trim());
  if (!m) return null;
  const [, dia, mes, ano] = m;
  return `${ano}-${mes}-${dia}`;
}

/** Valida "YYYY-MM-DD" e confere que a data existe de fato (rejeita 2026-02-30). */
export function validarIso(data) {
  const m = ISO.exec(String(data ?? '').trim());
  if (!m) return null;
  const [, ano, mes, dia] = m.map(Number);
  const d = new Date(Date.UTC(ano, mes - 1, dia));
  if (d.getUTCFullYear() !== ano || d.getUTCMonth() !== mes - 1 || d.getUTCDate() !== dia) {
    return null;
  }
  return { iso: `${m[1]}-${m[2]}-${m[3]}`, ano };
}

const DIAS = [
  'domingo', 'segunda-feira', 'terça-feira', 'quarta-feira',
  'quinta-feira', 'sexta-feira', 'sábado',
];

/** Dia da semana em português para uma data ISO, sem depender de locale do SO. */
export function diaDaSemana(iso) {
  const [ano, mes, dia] = iso.split('-').map(Number);
  return DIAS[new Date(Date.UTC(ano, mes - 1, dia)).getUTCDay()];
}
