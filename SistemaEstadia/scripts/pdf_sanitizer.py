#!/usr/bin/env python3
"""Sanitización de PDFs del corpus: suprime la primera página (carátula institucional).

Uso:
    pdf_sanitizer.py sanitizar ORIGEN DESTINO
    pdf_sanitizer.py contar ARCHIVO [ARCHIVO ...]

Siempre imprime un objeto JSON en la salida estándar. Termina con código 0 si
la operación tuvo éxito y con 1 si falló (el JSON incluye la clave "error").
"""
from __future__ import annotations

import json
import os
import sys
import tempfile

from pypdf import PdfReader, PdfWriter


class ErrorDePdf(Exception):
    """Error esperado que se informa al usuario sin traza técnica."""


def abrir(ruta: str) -> PdfReader:
    if not os.path.isfile(ruta):
        raise ErrorDePdf("El archivo no existe o no se puede leer.")
    try:
        lector = PdfReader(ruta)
        # Los PDFs cifrados solo con contraseña de propietario se abren con clave vacía.
        if lector.is_encrypted and not lector.decrypt(""):
            raise ErrorDePdf("El PDF está protegido con contraseña.")
        len(lector.pages)  # fuerza la lectura de la estructura para detectar archivos dañados
    except ErrorDePdf:
        raise
    except Exception as error:  # pypdf lanza varios tipos de excepción ante archivos dañados
        raise ErrorDePdf("No se pudo leer el PDF: {}".format(error)) from error
    return lector


def sanitizar(origen: str, destino: str) -> dict:
    lector = abrir(origen)
    total = len(lector.pages)
    if total < 2:
        raise ErrorDePdf(
            "El PDF solo tiene una página; al suprimir la portada no quedaría contenido."
        )

    escritor = PdfWriter()
    for pagina in lector.pages[1:]:
        escritor.add_page(pagina)

    # Se escribe en un temporal y luego se renombra para no dejar PDFs a medias.
    carpeta = os.path.dirname(os.path.abspath(destino))
    os.makedirs(carpeta, exist_ok=True)
    descriptor, temporal = tempfile.mkstemp(suffix=".pdf", dir=carpeta)
    try:
        with os.fdopen(descriptor, "wb") as salida:
            escritor.write(salida)
        os.replace(temporal, destino)
    except Exception:
        if os.path.exists(temporal):
            os.unlink(temporal)
        raise

    return {"paginas": total - 1}


def contar(rutas: list[str]) -> dict:
    paginas = {}
    for ruta in rutas:
        try:
            paginas[ruta] = len(abrir(ruta).pages)
        except ErrorDePdf:
            paginas[ruta] = None
    return {"paginas": paginas}


def main(argumentos: list[str]) -> int:
    try:
        if len(argumentos) == 4 and argumentos[1] == "sanitizar":
            resultado = sanitizar(argumentos[2], argumentos[3])
        elif len(argumentos) >= 3 and argumentos[1] == "contar":
            resultado = contar(argumentos[2:])
        else:
            raise ErrorDePdf(
                "Uso: pdf_sanitizer.py sanitizar ORIGEN DESTINO | contar ARCHIVO [ARCHIVO ...]"
            )
    except ErrorDePdf as error:
        print(json.dumps({"error": str(error)}, ensure_ascii=False))
        return 1
    except Exception as error:
        print(json.dumps({"error": "Error inesperado: {}".format(error)}, ensure_ascii=False))
        return 1

    print(json.dumps(resultado, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
