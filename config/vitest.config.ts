/// <reference types="vitest" />
import {defineConfig} from 'vitest/config'

export default defineConfig({
    test: {
        include: ['./src/Resources/app/**/*.spec.{js,ts}'],
        watch: false,
    },
})