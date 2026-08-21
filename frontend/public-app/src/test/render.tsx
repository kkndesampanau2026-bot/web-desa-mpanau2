import { render } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import type { ReactElement, ReactNode } from 'react'

/**
 * Merender komponen beserta provider yang dibutuhkan (TanStack Query & Router).
 *
 * `retry: false` penting: tanpa itu, test yang menguji keadaan galat akan
 * menunggu percobaan ulang dan berakhir timeout.
 */
export function renderDenganProvider(
  ui: ReactElement,
  { rute = '/', pola }: { rute?: string; pola?: string } = {},
) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0 },
    },
  })

  function Pembungkus({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={[rute]}>
          {pola ? (
            <Routes>
              <Route path={pola} element={children} />
            </Routes>
          ) : (
            children
          )}
        </MemoryRouter>
      </QueryClientProvider>
    )
  }

  return render(ui, { wrapper: Pembungkus })
}
