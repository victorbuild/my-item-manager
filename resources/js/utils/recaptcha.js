let scriptPromise

export const loadRecaptchaScript = siteKey => {
    if (window.grecaptcha?.ready) return Promise.resolve()
    if (scriptPromise) return scriptPromise
    scriptPromise = new Promise((resolve, reject) => {
        let script = document.getElementById('recaptcha-script')
        const fresh = !script
        if (fresh) {
            script = document.createElement('script')
            script.id = 'recaptcha-script'
            script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`
            script.async = true
        }
        const cleanup = () => { clearTimeout(timer); script.removeEventListener('load', success); script.removeEventListener('error', failure) }
        const success = () => { cleanup(); resolve() }
        const failure = () => { cleanup(); script.remove(); reject(new Error('驗證服務暫時無法使用，請重試。')) }
        const timer = setTimeout(failure, 15000)
        script.addEventListener('load', success, { once: true })
        script.addEventListener('error', failure, { once: true })
        if (fresh) document.head.appendChild(script)
    }).catch(error => { scriptPromise = undefined; throw error })
    return scriptPromise
}

export const getRecaptchaToken = async (siteKey, action) => {
    await loadRecaptchaScript(siteKey)
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('驗證逾時，請再試一次。')), 15000)
        const fail = () => { clearTimeout(timer); reject(new Error('驗證失敗，請再試一次。')) }
        try {
            window.grecaptcha.ready(async () => {
                try { const token = await window.grecaptcha.execute(siteKey, { action }); clearTimeout(timer); resolve(token) }
                catch { fail() }
            })
        } catch { fail() }
    })
}
