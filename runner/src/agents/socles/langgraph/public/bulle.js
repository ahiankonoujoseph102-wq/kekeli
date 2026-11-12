// Bulle de discussion à intégrer sur n'importe quel site : collez cette ligne avant </body> :
//   <script src="https://ADRESSE-DE-VOTRE-AGENT/bulle.js" defer></script>
// La bulle ouvre la page de l'agent dans un petit cadre ; aucune clé n'est jamais envoyée au navigateur.
(function () {
  var script = document.currentScript
  var origine = new URL(script.src).origin
  var ouvert = false
  var bouton = document.createElement('button')
  bouton.type = 'button'
  bouton.setAttribute('aria-label', 'Discuter avec l’assistant')
  bouton.textContent = '?'
  bouton.style.cssText = 'position:fixed;right:20px;bottom:20px;width:56px;height:56px;border-radius:50%;border:0;background:#1c1a17;color:#fff;font:600 22px system-ui,sans-serif;box-shadow:0 6px 20px rgba(0,0,0,.2);cursor:pointer;z-index:2147483646'
  var cadre = document.createElement('iframe')
  cadre.src = origine + '/'
  cadre.title = 'Assistant'
  cadre.style.cssText = 'position:fixed;right:20px;bottom:88px;width:min(380px,calc(100vw - 40px));height:min(560px,calc(100vh - 120px));border:1px solid #e7e2d9;border-radius:16px;background:#fff;box-shadow:0 12px 40px rgba(0,0,0,.18);display:none;z-index:2147483647'
  bouton.addEventListener('click', function () {
    ouvert = !ouvert
    cadre.style.display = ouvert ? 'block' : 'none'
    bouton.textContent = ouvert ? '×' : '?'
  })
  document.body.appendChild(cadre)
  document.body.appendChild(bouton)
})()
