<?php
/**
 * FUNCIÓN: pluralizeSpanish - Pluralización en español
 * ==================================================================================
 * Convierte palabras singulares al plural en español.
 * 
 * Reglas aplicadas:
 * - Terminación en vocal (a, e, i, o, u): agregar -s
 * - Terminación con tilde en ó/í: normalizar y aplicar regla
 * - Terminación en -ción: cambiar a -ciones
 * - Terminación en -sión: cambiar a -siones
 * - Terminación en consonante: agregar -es
 */
function pluralizeSpanish($word)
{
  // Normalizar: remover acentos para la lógica
  $normalized = str_replace(
    ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'],
    ['a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N'],
    $word
  );

  $normalized = strtolower($normalized);
  $wordLower = strtolower($word);

  $lastChar = substr($normalized, -1);
  $last2Chars = substr($normalized, -2);
  $last3Chars = substr($normalized, -3);
  $last4Chars = substr($normalized, -4);

  // Terminación en -ción → -ciones (con o sin tilde)
  if ($last4Chars === 'cion' || substr($wordLower, -4) === 'ción') {
    return substr($word, 0, -4) . 'ciones';
  }

  // Terminación en -sión → -siones
  if ($last4Chars === 'sion' || substr($wordLower, -4) === 'sión') {
    return substr($word, 0, -4) . 'siones';
  }

  // Terminación en -z → -ces
  if ($lastChar === 'z') {
    return substr($word, 0, -1) . 'ces';
  }

  // Vocales (a, e, i, o, u) y vocales con tilde → agregar -s
  $lastCharOriginal = substr($wordLower, -1);
  if (
    in_array($lastChar, ['a', 'e', 'i', 'o', 'u']) ||
    in_array($lastCharOriginal, ['á', 'é', 'í', 'ó', 'ú'])
  ) {
    return $word . 's';
  }

  // Consonantes → agregar -es
  return $word . 'es';
}