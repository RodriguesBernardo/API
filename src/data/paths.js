import { fileURLToPath } from 'node:url';
import path from 'node:path';

const AQUI = path.dirname(fileURLToPath(import.meta.url));

/** Raiz do projeto (dois níveis acima de src/data). */
export const RAIZ = path.resolve(AQUI, '..', '..');

/** Pasta de dados do repositório feriados-brasil. */
export const DADOS = path.join(RAIZ, 'feriados-brasil', 'dados');

export const DIR_FERIADOS = path.join(DADOS, 'feriados');
export const DIR_LOCALIZACAO = path.join(DADOS, 'localizacao');
