const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: __dirname,
  workers: 1,
  reporter: 'list',
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8080'
  }
});
