const twColors = require('tailwindcss/colors')

// Nova's colour scheme (vendor/laravel/nova/generators.js): every shade reads
// the `--colors-*` variable Nova's app.css defines, primary is sky and gray is
// slate. Inlined rather than required, since that file resolves lodash from
// Nova's own node_modules. Only the utilities this component uses are built,
// without preflight: Nova ships both already.
const palette = { primary: twColors.sky, ...twColors, gray: twColors.slate }

const colors = Object.fromEntries(
  Object.entries(palette)
    .filter(([key]) => !['lightBlue', 'warmGray', 'trueGray', 'coolGray', 'blueGray'].includes(key))
    .map(([key, value]) =>
      typeof value === 'string'
        ? [key, value]
        : [key, Object.fromEntries(Object.keys(value).map(shade => [shade, `rgba(var(--colors-${key}-${shade}))`]))]
    )
)

module.exports = {
  content: ['./resources/js/**/*.vue'],
  darkMode: 'class',
  corePlugins: { preflight: false },
  theme: {
    colors,
    extend: { fontSize: { xxs: '11px' } },
  },
}
