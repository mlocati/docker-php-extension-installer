import commonjs from '@rollup/plugin-commonjs';
import nodeResolve from '@rollup/plugin-node-resolve';

// https://rollupjs.org/configuration-options
/** @type {import('rollup').RollupOptions} */
const config = {
  input: 'src/main.js',
  output: {
    file: 'dist/main.js',
    format: 'es',
    generatedCode: 'es2015',
    interop: 'esModule',
    sourcemap: false,
    validate: true,
    esModule: true,
  },
  plugins: [nodeResolve({preferBuiltins: true}), commonjs()],
  context: undefined,
  moduleContext: undefined,
  onwarn(warning, handler) {
    if (warning.code === 'THIS_IS_UNDEFINED' || warning.code === 'CIRCULAR_DEPENDENCY') {
      return;
    }
    handler(warning);
  },
};

export default config;
