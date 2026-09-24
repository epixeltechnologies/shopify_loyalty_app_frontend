module.exports = {
  root: true,
  env: { browser: true, es2021: true },
  extends: [
    'eslint:recommended',
    'plugin:@typescript-eslint/recommended',
    'plugin:react-hooks/recommended',
  ],
  ignorePatterns: ['dist', '.eslintrc.cjs'],
  parser: '@typescript-eslint/parser',
  plugins: ['react-refresh'],
  rules: {
    // Provider files legitimately export both a component and its
    // paired hook (e.g. ShopifyBridgeProvider + useShopifyBridge) — that
    // pattern only costs a slower Fast Refresh, never correctness, so it
    // is downgraded to non-fatal rather than split across files.
    'react-refresh/only-export-components': 'off',
  },
};
