import { Head } from '@inertiajs/react'

export interface SeoMetaProps {
  title?: string
  description?: string
  keywords?: string
  ogImage?: string
  ogType?: 'website' | 'article' | 'profile' | 'place'
  canonical?: string
  noIndex?: boolean
  schema?: Record<string, unknown> | Array<Record<string, unknown>>
}

/**
 * Komponen SEO & AEO tunggal untuk seluruh halaman publik.
 *
 * Mengatur metadata dokumen, OpenGraph, Twitter Card, dan structured data
 * JSON-LD (Schema.org) untuk Answer Engine Optimization (ChatGPT, Perplexity, Gemini, Claude)
 * serta perayap Google Search Console.
 */
export function SeoMeta({
  title,
  description,
  keywords,
  ogImage,
  ogType = 'website',
  canonical,
  noIndex = false,
  schema,
}: SeoMetaProps) {
  const metaDescription =
    description ??
    'Website Resmi Profil dan Pelayanan Digital Desa Mpanau, Kecamatan Sigi Biromaru, Kabupaten Sigi, Provinsi Sulawesi Tengah.'

  return (
    <Head title={title}>
      {noIndex ? (
        <meta name="robots" content="noindex, nofollow" head-key="robots" />
      ) : (
        <meta
          name="robots"
          content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1"
          head-key="robots"
        />
      )}

      <meta name="description" content={metaDescription} head-key="description" />
      {keywords && <meta name="keywords" content={keywords} head-key="keywords" />}
      {canonical && <link rel="canonical" href={canonical} head-key="canonical" />}

      {/* Open Graph */}
      <meta property="og:type" content={ogType} head-key="og:type" />
      {title && <meta property="og:title" content={title} head-key="og:title" />}
      <meta property="og:description" content={metaDescription} head-key="og:description" />
      {ogImage && <meta property="og:image" content={ogImage} head-key="og:image" />}

      {/* Twitter Cards */}
      <meta name="twitter:card" content="summary_large_image" head-key="twitter:card" />
      {title && <meta name="twitter:title" content={title} head-key="twitter:title" />}
      <meta name="twitter:description" content={metaDescription} head-key="twitter:description" />
      {ogImage && <meta name="twitter:image" content={ogImage} head-key="twitter:image" />}

      {/* Structured Data (JSON-LD) AEO */}
      {schema && (
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{
            __html: JSON.stringify(schema),
          }}
          head-key="ld-json"
        />
      )}
    </Head>
  )
}
