import fs from 'node:fs';
import path from 'node:path';
import { DIR_FERIADOS } from './paths.js';
import { brParaIso } from '../lib/datas.js';

const TIPOS = ['nacional', 'estadual', 'municipal', 'facultativo'];

/** Índices já montados, por ano. Preenchido sob demanda. */
const cache = new Map();

let anosDisponiveis = null;

/** Anos com arquivo em todos os tipos — lido do disco uma vez. */
export function anos() {
  if (anosDisponiveis) return anosDisponiveis;

  const porTipo = TIPOS.map((tipo) => {
    const dir = path.join(DIR_FERIADOS, tipo, 'json');
    return new Set(
      fs.readdirSync(dir)
        .filter((f) => /^\d{4}\.json$/.test(f))
        .map((f) => Number(f.slice(0, 4))),
    );
  });

  const lista = [...porTipo[0]]
    .filter((ano) => porTipo.every((s) => s.has(ano)))
    .sort((a, b) => a - b);

  anosDisponiveis = { lista, min: lista[0], max: lista[lista.length - 1] };
  return anosDisponiveis;
}

export function anoSuportado(ano) {
  const { min, max } = anos();
  return Number.isInteger(ano) && ano >= min && ano <= max;
}

function lerTipo(tipo, ano) {
  const arquivo = path.join(DIR_FERIADOS, tipo, 'json', `${ano}.json`);
  try {
    return JSON.parse(fs.readFileSync(arquivo, 'utf8'));
  } catch (erro) {
    if (erro.code === 'ENOENT') return [];
    throw erro;
  }
}

/**
 * Índice de um ano: Map<"YYYY-MM-DD", Feriado[]>.
 * Monta na primeira chamada e guarda em cache.
 */
export function indicePorAno(ano) {
  if (cache.has(ano)) return cache.get(ano);

  const indice = new Map();

  for (const tipo of TIPOS) {
    for (const bruto of lerTipo(tipo, ano)) {
      const iso = brParaIso(bruto.data);
      if (!iso) continue;

      const feriado = {
        data: iso,
        nome: bruto.nome,
        tipo,
        descricao: bruto.descricao || null,
        uf: bruto.uf ? bruto.uf.toUpperCase() : null,
        codigoIbge: bruto.codigo_ibge ?? null,
      };

      if (!indice.has(iso)) indice.set(iso, []);
      indice.get(iso).push(feriado);
    }
  }

  cache.set(ano, indice);
  return indice;
}

/**
 * Filtra os feriados aplicáveis a um local.
 * Nacional vale sempre; estadual exige a UF; municipal exige o código IBGE.
 */
export function aplicaveis(feriados, { uf = null, codigoIbge = null } = {}) {
  return feriados.filter((f) => {
    if (f.tipo === 'nacional' || f.tipo === 'facultativo') {
      // Facultativo estadual/municipal também existe nos dados.
      if (f.codigoIbge != null) return f.codigoIbge === codigoIbge;
      if (f.uf) return f.uf === uf;
      return true;
    }
    if (f.tipo === 'estadual') return uf != null && f.uf === uf;
    if (f.tipo === 'municipal') return codigoIbge != null && f.codigoIbge === codigoIbge;
    return false;
  });
}

/** Todos os feriados do ano aplicáveis ao local, ordenados por data. */
export function feriadosDoAno(ano, local) {
  const indice = indicePorAno(ano);
  const saida = [];
  for (const lista of indice.values()) {
    saida.push(...aplicaveis(lista, local));
  }
  return saida.sort((a, b) => a.data.localeCompare(b.data) || a.nome.localeCompare(b.nome, 'pt-BR'));
}

/** Feriados aplicáveis numa data ISO específica. */
export function feriadosNaData(iso, ano, local) {
  const lista = indicePorAno(ano).get(iso) ?? [];
  return aplicaveis(lista, local);
}

export function anosEmCache() {
  return [...cache.keys()].sort((a, b) => a - b);
}
