import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

// Membersihkan DOM antar-test agar hasil satu test tidak bocor ke test lain.
afterEach(() => {
  cleanup()
})
