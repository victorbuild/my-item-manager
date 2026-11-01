import * as THREE from 'three'
import { createModels, ease } from './itemStatusModels.js'

export const createItemStatusScene = ({ container, state, reducedMotion = false, onError }) => {
    if (typeof ResizeObserver !== 'function') throw new Error('瀏覽器不支援場景尺寸監測。')
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' })
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5))
    renderer.setClearColor(0, 0)
    renderer.domElement.setAttribute('aria-hidden', 'true')
    container.append(renderer.domElement)
    const scene = new THREE.Scene()
    const camera = new THREE.OrthographicCamera(-2, 2, 1.6, -1.6, .1, 40)
    camera.position.set(3.2, 2.3, 5.5); camera.lookAt(0, .85, 0)
    camera.zoom = .75
    scene.add(new THREE.HemisphereLight(0xffffff, 0xdde5eb, 2.2))
    const light = new THREE.DirectionalLight(0xffffff, 2.5); light.position.set(-3, 6, 5); scene.add(light)
    const colors = () => {
        const css = getComputedStyle(container)
        return Object.fromEntries(['brand', 'surface', 'text', 'text-muted', 'border-control', 'illustration-skin', 'illustration-paper', 'illustration-wood'].map(key => [key, css.getPropertyValue(`--${key}`).trim()]))
    }
    const models = createModels(scene, colors())
    models.pedestal.visible = false
    let current = state, pose = models.poseFor(state, 1), from = pose
    let active = false, disposed = false, frame = null, last = null, elapsed = 2400
    const draw = () => { models.apply(pose); renderer.render(scene, camera) }
    const schedule = () => { if (active && !disposed && frame == null) frame = requestAnimationFrame(render) }
    const render = time => {
        frame = null
        if (!active || disposed) return
        try {
            const delta = last == null ? 0 : Math.min(40, time - last); last = time
            if (elapsed < 2400) {
                elapsed = Math.min(2400, elapsed + delta)
                pose = elapsed < 300 ? models.blend(from, models.poseFor(current, 0), ease(elapsed / 300))
                    : models.poseFor(current, (elapsed - 300) / 2100)
            }
            draw()
            if (elapsed < 2400) schedule()
        } catch { dispose(); onError?.() }
    }
    const resize = () => {
        if (disposed) return
        const w = Math.max(1, container.clientWidth), h = Math.max(1, container.clientHeight), aspect = w / h, extent = Math.max(1.45, 1.9 / aspect)
        renderer.setSize(w, h, false)
        camera.top = extent; camera.bottom = -extent; camera.left = -extent * aspect; camera.right = extent * aspect; camera.updateProjectionMatrix()
        schedule()
    }
    const observer = new ResizeObserver(resize)
    const contextLost = event => { event.preventDefault(); dispose(); onError?.() }
    const dispose = () => {
        if (disposed) return
        disposed = true
        if (frame != null) cancelAnimationFrame(frame)
        observer.disconnect(); renderer.domElement.removeEventListener('webglcontextlost', contextLost)
        const geometries = new Set(), materials = new Set()
        scene.traverse(object => { if (object.geometry) geometries.add(object.geometry); if (object.material) materials.add(object.material) })
        geometries.forEach(geometry => geometry.dispose()); materials.forEach(material => material.dispose())
        renderer.dispose(); renderer.forceContextLoss(); renderer.domElement.remove()
    }
    renderer.domElement.addEventListener('webglcontextlost', contextLost)
    observer.observe(container); resize()
    return {
        setActive(value) { active = value; last = null; if (!active && frame != null) { cancelAnimationFrame(frame); frame = null } if (active) schedule() },
        setState(value) {
            if (value === current || disposed) return
            from = pose; current = value; last = null; elapsed = reducedMotion ? 2400 : 0
            if (reducedMotion) pose = models.poseFor(value, 1)
            schedule()
        },
        setReducedMotion(value) { reducedMotion = value; if (value) { elapsed = 2400; pose = models.poseFor(current, 1); schedule() } },
        refreshColors() {
            const palette = colors()
            scene.traverse(object => { if (object.material?.userData.role) object.material.color.set(palette[object.material.userData.role]) })
            schedule()
        },
        dispose,
    }
}
