import js from "@eslint/js";
import vue from "eslint-plugin-vue";
import globals from "globals";
import unusedImports from 'eslint-plugin-unused-imports'

export default [
  {
    ignores: [
      "vendor/**",
      "node_modules/**",
      "public/build/**",
      "bootstrap/cache/**",
      "storage/**",
    ],
  },
  js.configs.recommended,
  ...vue.configs["flat/essential"],
  {
    
    files: ["resources/js/**/*.{js,vue}", "vite.config.js"],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
    rules: {
      "vue/multi-word-component-names": "off",
      'no-unused-vars': 'off',
      'unused-imports/no-unused-imports': 'error',
      'unused-imports/no-unused-vars': 'error',
    },
    plugins: {
        'unused-imports': unusedImports,
    }
  },
];
