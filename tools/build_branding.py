#!/usr/bin/env python3
"""Build pix/branding.svg: the "eduzhub.ro" wordmark with "designed by Burkay ALGUL" beneath it,
both set in Saira (SIL Open Font License 1.1) and converted to outlines, so no font is needed.
Usage: python3 -I tools/build_branding.py <Saira-VF.ttf> <out.svg>
Requires fontTools. Not shipped in the plugin zip.
"""
import sys

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.varLib.instancer import instantiateVariableFont
from fontTools.ttLib import TTFont

WORD, WORD_WGHT = "eduzhub.ro", 600
SIG, SIG_WGHT = "designed by Burkay ALGUL", 500
WIDTH = 1000          # wordmark width in SVG units
SIG_WIDTH = 820       # signature width, centred under the wordmark
GAP = 70              # space between wordmark baseline and signature cap height
FILL = "#0a1d3f"


def instance(path, wght):
    return instantiateVariableFont(TTFont(path), {"wght": wght, "wdth": 100})


def line(font, text, width, x0, baseline):
    """Outline paths for text scaled to the given width; returns (svg, scale)."""
    cmap, glyphs, hmtx = font.getBestCmap(), font.getGlyphSet(), font["hmtx"]
    names = [cmap[ord(c)] for c in text]
    scale = width / sum(hmtx[n][0] for n in names)
    x, parts = x0, []
    for n in names:
        pen = SVGPathPen(glyphs)
        glyphs[n].draw(pen)
        if pen.getCommands():
            parts.append(f'<path transform="translate({x:.2f} {baseline:.2f}) scale({scale:.5f} {-scale:.5f})" '
                         f'd="{pen.getCommands()}"/>')
        x += hmtx[n][0] * scale
    return "\n".join(parts), scale


def main(vf, out):
    word_font, sig_font = instance(vf, WORD_WGHT), instance(vf, SIG_WGHT)
    asc = word_font["OS/2"].sCapHeight or 700
    # Wordmark: its ascender (letters d, h, b) sets the top edge.
    probe = word_font.getBestCmap()
    tall = word_font["glyf"][probe[ord("d")]]
    _, wscale = line(word_font, WORD, WIDTH, 0, 0)
    word_base = tall.yMax * wscale
    word_svg, _ = line(word_font, WORD, WIDTH, 0, word_base)
    sig_tmp, sscale = line(sig_font, SIG, SIG_WIDTH, 0, 0)
    sig_cap = (sig_font["OS/2"].sCapHeight or asc) * sscale
    sig_base = word_base + GAP + sig_cap
    sig_svg, _ = line(sig_font, SIG, SIG_WIDTH, (WIDTH - SIG_WIDTH) / 2, sig_base)
    desc = abs(sig_font["glyf"][sig_font.getBestCmap()[ord("y")]].yMin) * sscale
    height = sig_base + desc
    svg = (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {WIDTH} {height:.0f}" '
           f'width="{WIDTH}" height="{height:.0f}">\n<g fill="{FILL}">\n{word_svg}\n{sig_svg}\n</g>\n</svg>\n')
    open(out, "w", encoding="utf-8").write(svg)


if __name__ == "__main__":
    main(*sys.argv[1:3])
