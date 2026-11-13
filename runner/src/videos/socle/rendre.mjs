// Rendu de la vidéo écrite par Kekeli (moteur Revideo, licence MIT), dans l'ordinateur isolé, sans Internet.
// Écrit une ligne « PROGRESSION 0.42 » de temps en temps (lue par le Runner pour « Rendu… 42 % »), puis « RENDU <fichier> ».
import { renderVideo } from '@revideo/renderer'

const navigateur = process.env.KEKELI_NAVIGATEUR
let dernier = -1
const fichier = await renderVideo({
  projectFile: './src/projet.ts',
  settings: {
    outFile: 'images.mp4',
    outDir: './sortie',
    logProgress: false,
    progressCallback: (_ouvrier, progression) => {
      const p = Math.floor(progression * 100)
      if (p >= dernier + 5 || p === 100) {
        dernier = p
        console.log(`PROGRESSION ${(p / 100).toFixed(2)}`)
      }
    },
    // Les modules du moteur sont préparés dès le départ (sinon le premier rendu est interrompu pour les réorganiser).
    viteConfig: { optimizeDeps: { include: ['@revideo/2d', '@revideo/core', '@revideo/renderer/lib/client/render'] }, logLevel: 'error' },
    puppeteer: { executablePath: navigateur, args: ['--no-sandbox', '--disable-dev-shm-usage', '--mute-audio'] },
  },
})
console.log(`RENDU ${fichier}`)
