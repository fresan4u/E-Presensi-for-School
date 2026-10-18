/**
 * useFaceScanner
 * ------------------------------------------------------------------
 * Pembungkus face-api.js untuk pemindaian wajah di browser.
 *
 * Catatan penting:
 * - face-api.js hanya diimpor secara DINAMIS (di dalam fungsi) supaya aman
 *   saat SSR/prerender Nuxt — library ini memakai `navigator`/`window`.
 * - Model dimuat dari folder statis `public/models` sehingga diakses dari `/models`.
 * - Descriptor wajah (128 angka) di-log sebagai JSON, siap dikirim ke backend.
 */

export type FaceScannerStatus = 'idle' | 'loading' | 'ready' | 'scanning' | 'detected' | 'error'

export interface FaceScanResult {
  /** 128 angka descriptor wajah. */
  descriptor: number[]
  /** Descriptor dalam bentuk JSON string (siap dikirim ke backend). */
  json: string
  /** Waktu deteksi, format HH:mm:ss. */
  detectedAt: string
}

type FaceApiModule = typeof import('face-api.js')

const MODELS_URI = '/models'
const SCAN_INTERVAL_MS = 250

const timeNow = () =>
  new Intl.DateTimeFormat('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  }).format(new Date())

/** Terjemahkan error kamera jadi pesan yang bisa dibaca pengguna. */
const describeError = (error: unknown): string => {
  const name = (error as { name?: string })?.name ?? ''
  const message = (error as { message?: string })?.message ?? ''

  switch (name) {
    case 'NotAllowedError':
    case 'PermissionDeniedError':
      return 'Izin kamera ditolak. Izinkan akses kamera lewat browser, lalu coba lagi.'
    case 'NotFoundError':
    case 'DevicesNotFoundError':
      return 'Kamera tidak ditemukan di perangkat ini.'
    case 'NotReadableError':
    case 'TrackStartError':
      return 'Kamera sedang dipakai aplikasi lain. Tutup aplikasi lain, lalu coba lagi.'
    case 'OverconstrainedError':
      return 'Kamera tidak mendukung resolusi yang diminta.'
    case 'AbortError':
      return 'Pengambilan kamera dibatalkan.'
    case 'InsecureContextError':
      return message
    default:
      if (message.toLowerCase().includes('model')) {
        return 'Gagal memuat model pengenalan wajah. Pastikan folder /models tersedia, lalu coba lagi.'
      }
      return message || 'Terjadi kesalahan saat mengakses kamera.'
  }
}

export function useFaceScanner() {
  const videoRef = ref<HTMLVideoElement | null>(null)
  const status = ref<FaceScannerStatus>('idle')
  const errorMessage = ref('')
  const result = ref<FaceScanResult | null>(null)

  let faceapi: FaceApiModule | null = null
  let modelsLoaded = false
  let stream: MediaStream | null = null
  let scanTimer: ReturnType<typeof setInterval> | null = null
  let detecting = false

  const stopTimer = () => {
    if (scanTimer) clearInterval(scanTimer)
    scanTimer = null
  }

  const releaseStream = () => {
    stream?.getTracks().forEach((track) => track.stop())
    stream = null
    if (videoRef.value) videoRef.value.srcObject = null
  }

  /** Muat 3 model face-api dari `/models` (cukup sekali). */
  const loadModels = async () => {
    if (modelsLoaded && faceapi) return
    if (!faceapi) faceapi = await import('face-api.js')

    const { tinyFaceDetector, faceLandmark68Net, faceRecognitionNet } = faceapi.nets
    await Promise.all([
      tinyFaceDetector.loadFromUri(MODELS_URI),
      faceLandmark68Net.loadFromUri(MODELS_URI),
      faceRecognitionNet.loadFromUri(MODELS_URI),
    ])
    modelsLoaded = true
  }

  const startCamera = async () => {
    if (stream) return

    if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
      throw Object.assign(
        new Error('Browser tidak mendukung akses kamera. Gunakan localhost atau HTTPS.'),
        { name: 'InsecureContextError' },
      )
    }

    stream = await navigator.mediaDevices.getUserMedia({
      video: { width: 640, height: 480, facingMode: 'user' },
      audio: false,
    })

    const video = videoRef.value
    if (!video) {
      releaseStream()
      throw new Error('Elemen video tidak ditemukan.')
    }

    video.srcObject = stream
    // Tunggu video benar-benar bisa diputar sebelum deteksi frame.
    await video.play()
  }

  const stopCamera = () => {
    stopTimer()
    releaseStream()
    detecting = false
    if (status.value !== 'idle') status.value = 'idle'
  }

  /** Muat model + nyalakan kamera. Panggil saat `onMounted`. */
  const init = async () => {
    status.value = 'loading'
    errorMessage.value = ''
    result.value = null

    try {
      await loadModels()
      await startCamera()
      status.value = 'ready'
    } catch (error) {
      status.value = 'error'
      errorMessage.value = describeError(error)
      console.error('[useFaceScanner] gagal inisialisasi:', error)
    }
  }

  const runDetection = async () => {
    const video = videoRef.value
    if (!faceapi || !video || detecting || video.readyState < 2) return

    detecting = true
    try {
      const detection = await faceapi
        .detectSingleFace(
          video,
          new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 }),
        )
        .withFaceLandmarks()
        .withFaceDescriptor()

      // Wajah belum terdeteksi: biarkan interval jalan terus.
      if (!detection) return

      // Wajah terdeteksi: berhenti, simpan descriptor, log JSON.
      stopTimer()

      const descriptor = Array.from(detection.descriptor) // Float32Array -> number[128]
      const json = JSON.stringify(descriptor)

      console.log('[useFaceScanner] JSON descriptor wajah terdeteksi:', json)

      result.value = { descriptor, json, detectedAt: timeNow() }
      status.value = 'detected'
    } catch (error) {
      stopTimer()
      status.value = 'error'
      errorMessage.value = 'Gagal memproses frame kamera. Silakan coba lagi.'
      console.error('[useFaceScanner] error deteksi:', error)
    } finally {
      detecting = false
    }
  }

  const startScan = () => {
    if (!faceapi || !videoRef.value) return
    stopTimer()
    result.value = null
    status.value = 'scanning'
    scanTimer = setInterval(runDetection, SCAN_INTERVAL_MS)
  }

  const stopScan = () => {
    stopTimer()
    if (status.value === 'scanning') status.value = 'ready'
  }

  onUnmounted(() => {
    stopCamera()
  })

  return {
    videoRef,
    status,
    errorMessage,
    result,
    init,
    startScan,
    stopScan,
    stopCamera,
  }
}
