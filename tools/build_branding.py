#!/usr/bin/env python3
"""Build pix/branding.svg: the full EduZ logo cropped from the owner's Logos.svg sheet,
with "designed by Burkay ALGUL" in Saira Medium (OFL) converted to outlines beneath it.

Usage: python3 -I tools/build_branding.py <Logos.svg> <Saira-Medium.ttf> <out.svg>
Requires fontTools. Not shipped in the plugin zip.
"""
import re
import sys

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.ttLib import TTFont

CROP_X, CROP_Y, WIDTH, LOGO_H = 186, 132, 1110, 298
TEXT = "designed by Burkay ALGUL"
TEXT_WIDTH = WIDTH / 2
BASELINE = 362
TOTAL_H = 372
FILL = "#0a1d3f"


def text_paths(font_path):
    font = TTFont(font_path)
    cmap = font.getBestCmap()
    glyphset = font.getGlyphSet()
    hmtx = font["hmtx"]
    names = [cmap[ord(ch)] for ch in TEXT]
    advance = sum(hmtx[n][0] for n in names)
    scale = TEXT_WIDTH / advance
    x = CROP_X + (WIDTH - TEXT_WIDTH) / 2
    parts = []
    for n in names:
        pen = SVGPathPen(glyphset)
        glyphset[n].draw(pen)
        d = pen.getCommands()
        if d:
            parts.append(
                f'<path transform="translate({x:.3f} {CROP_Y + BASELINE}) scale({scale:.6f} {-scale:.6f})" d="{d}"/>'
            )
        x += hmtx[n][0] * scale
    return "\n".join(parts)


def main(sheet, font_path, out):
    svg = open(sheet, encoding="utf-8").read()
    svg = re.sub(r"<sodipodi:namedview.*?/>", "", svg, flags=re.S)
    svg = re.sub(r"<metadata.*?</metadata>", "", svg, flags=re.S)
    svg = re.sub(r'\s(inkscape|sodipodi):[a-zA-Z-]+="[^"]*"', "", svg)
    svg = re.sub(r'\sxmlns:(inkscape|sodipodi)="[^"]*"', "", svg)
    svg = re.sub(r'width="[^"]*mm"', f'width="{WIDTH}"', svg, count=1)
    svg = re.sub(r'height="[^"]*mm"', f'height="{TOTAL_H}"', svg, count=1)
    svg = re.sub(r'viewBox="[^"]*"', f'viewBox="{CROP_X} {CROP_Y} {WIDTH} {TOTAL_H}"', svg, count=1)
    clip = (f'<clipPath id="pf-crop"><rect x="{CROP_X}" y="{CROP_Y}" width="{WIDTH}" height="{LOGO_H}"/></clipPath>')
    # Wrap original content in a clip so other logos on the sheet never bleed in.
    opening_end = svg.index(">", svg.index("<svg")) + 1
    svg = (svg[:opening_end] + f"<defs>{clip}</defs><g clip-path=\"url(#pf-crop)\">"
           + svg[opening_end:].replace("</svg>", "")
           + f'</g>\n<g fill="{FILL}">\n{text_paths(font_path)}\n</g>\n</svg>\n')
    open(out, "w", encoding="utf-8").write(svg)


if __name__ == "__main__":
    main(*sys.argv[1:4])
