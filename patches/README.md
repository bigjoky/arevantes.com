# Static Display P3 colors in SVG PDFs

`php-svg-lib-display-p3.patch` extends php-svg-lib 1.0.2's color parser for the
static `color(display-p3 R G B)` notation used by the supplied official SVG.
It does not rewrite or generate an SVG, alter paths, or rasterize the logo.

The PDF backend outputs sRGB vectors. The patch decodes the shared sRGB/P3
transfer function, converts linear P3 through D65 XYZ into linear sRGB, then
encodes the output channels. Values at the output gamut boundary are clipped
as required for that backend. This is output color-space conversion, not a
new brand palette. It is not a general implementation of every CSS Color 4
syntax or an archival wide-gamut ICC PDF exporter.

Matrices and transfer functions: W3C CSS Color 4, sample color conversions:
https://www.w3.org/TR/2026/CRD-css-color-4-20261009/#color-conversion-code

Composer Patches installs the change reproducibly with a checked SHA-256 in
`patches.lock.json`. When upgrading php-svg-lib, confirm patch applicability
and run `SvgDisplayP3Test`; remove the patch once upstream supports this syntax.
For an existing vendor installation when first adding this plugin, run
`composer patches-repatch` once; clean installs apply the locked patch normally.

Verification checks both official colors against standard sRGB display values,
checks the source SVG hash, and asserts colored vector commands in uncompressed
PDF output with no raster image objects. CompanyBrandPdfTest verifies sales and
purchase templates/services; QuotePdfTest covers proposals and continuation
headers. Visually inspect generated PDF pages after any renderer change.
