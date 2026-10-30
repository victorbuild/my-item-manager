import * as THREE from 'three'

export const ease = t => { t = Math.max(0, Math.min(1, t)); return t * t * (3 - 2 * t) }
const phase = (t, a, b) => ease((t - a) / (b - a))
const mix = (a, b, t) => a + (b - a) * t
const vector = (a, b, t) => a.map((v, i) => mix(v, b[i], t))
export function createModels(scene, colors) {
    const mesh = (geometry, role, parent, position = [0, 0, 0]) => {
        const m = new THREE.Mesh(geometry, new THREE.MeshStandardMaterial({ color: colors[role], roughness: .85, flatShading: true }))
        m.material.userData.role = role
        m.position.fromArray(position); m.castShadow = true; m.receiveShadow = true; parent.add(m); return m
    }
    const box = (w, h, d, role, p, xyz) => mesh(new THREE.BoxGeometry(w, h, d), role, p, xyz)
    const ball = (r, role, p, xyz) => mesh(new THREE.SphereGeometry(r, 16, 12), role, p, xyz)
    const cylinder = (a, b, h, role, p, xyz, open = false) => mesh(new THREE.CylinderGeometry(a, b, h, 24, 1, open), role, p, xyz)
    const pedestal = cylinder(2.25, 2.28, .08, 'surface', scene, [0, -.06, 0])
    const actor = new THREE.Group(); scene.add(actor)
    box(.43, .48, .30, 'brand', actor, [0, .81, 0])
    cylinder(.095, .095, .14, 'illustration-skin', actor, [0, 1.10, 0])
    const head = new THREE.Group(); actor.add(head); head.position.y = 1.42
    ball(.28, 'illustration-skin', head)
    mesh(new THREE.SphereGeometry(.286, 16, 9, 0, Math.PI * 2, 0, Math.PI * .43), 'text', head, [0, .018, 0])
    for (const side of [-1, 1]) ball(.022, 'text', head, [side * .095, -.015, .255])
    const brows = [-1, 1].map(side => box(.085, .018, .026, 'text', head, [side * .095, .06, .26]))
    const mouth = box(.075, .018, .022, 'text', head, [0, -.105, .26])
    const expression = curve => mesh(new THREE.TubeGeometry(curve, 10, .009, 6, false), 'text', head)
    const smile = expression(new THREE.QuadraticBezierCurve3(new THREE.Vector3(-.055, -.10, .263), new THREE.Vector3(0, -.16, .276), new THREE.Vector3(.055, -.10, .263)))
    const frown = expression(new THREE.QuadraticBezierCurve3(new THREE.Vector3(-.05, -.13, .263), new THREE.Vector3(0, -.08, .276), new THREE.Vector3(.05, -.13, .263)))
    const legs = [-1, 1].map(side => {
        const pivot = new THREE.Group(); actor.add(pivot); pivot.position.set(side * .12, .58, 0)
        cylinder(.068, .062, .45, 'text-muted', pivot, [0, -.23, 0]); box(.18, .12, .25, 'text', pivot, [0, -.51, .04]); return pivot
    })
    const arms = [-1, 1].map(side => {
        const shoulder = new THREE.Vector3(side * .265, 1.015, 0)
        ball(.075, 'illustration-skin', actor, shoulder.toArray())
        const upper = cylinder(.061, .055, 1, 'illustration-skin', actor)
        const lower = cylinder(.055, .052, 1, 'illustration-skin', actor)
        const elbowJoint = ball(.058, 'illustration-skin', actor)
        const hand = new THREE.Group(); actor.add(hand)
        const fist = ball(.07, 'illustration-skin', hand)
        const fingers = new THREE.Group(); hand.add(fingers)
        ball(.058, 'illustration-skin', fingers, [0, .015, 0])
        for (const fingerSide of [-1, 1]) {
            const finger = cylinder(.019, .021, .15, 'illustration-skin', fingers, [fingerSide * .037, -.07, 0])
            finger.rotation.z = fingerSide * .16
        }
        return { side, shoulder, upper, lower, elbowJoint, hand, fist, fingers, endpoint: new THREE.Vector3(), elbow: new THREE.Vector3() }
    })
    const axisY = new THREE.Vector3(0, 1, 0)
    const bone = (object, start, end) => {
        const direction = end.clone().sub(start)
        object.position.copy(start).add(end).multiplyScalar(.5)
        object.quaternion.setFromUnitVectors(axisY, direction.clone().normalize())
        object.scale.y = direction.length()
    }
    const solveArm = (rig, target, pinch) => {
        const end = new THREE.Vector3().fromArray(target), direction = end.clone().sub(rig.shoulder)
        const distance = direction.length(), upperLength = .30, lowerLength = .29
        if (distance > upperLength + lowerLength + .001) throw new Error(`手部超出手臂可及範圍：${distance.toFixed(3)}`)
        const d = Math.max(.001, distance); direction.normalize()
        const a = (upperLength ** 2 - lowerLength ** 2 + d ** 2) / (2 * d)
        const bend = new THREE.Vector3(rig.side * .2, -1, .15)
        bend.addScaledVector(direction, -bend.dot(direction)).normalize()
        const elbow = rig.shoulder.clone().addScaledVector(direction, a).addScaledVector(bend, Math.sqrt(Math.max(0, upperLength ** 2 - a ** 2)))
        bone(rig.upper, rig.shoulder, elbow); bone(rig.lower, elbow, end)
        rig.elbowJoint.position.copy(elbow); rig.hand.position.copy(end)
        rig.fist.visible = !pinch; rig.fingers.visible = pinch
        rig.endpoint.copy(end); rig.elbow.copy(elbow)
    }
    const parcel = new THREE.Group(); scene.add(parcel)
    box(.32, .26, .26, 'illustration-paper', parcel)
    box(.04, .264, .264, 'illustration-wood', parcel)
    const handle = new THREE.Group(); parcel.add(handle)
    cylinder(.018, .018, .14, 'illustration-wood', handle, [-.065, .20, 0])
    cylinder(.018, .018, .14, 'illustration-wood', handle, [.065, .20, 0])
    box(.15, .025, .035, 'illustration-wood', handle, [0, .27, 0])
    const bin = new THREE.Group(); scene.add(bin)
    const wall = cylinder(.28, .23, .62, 'border-control', bin, [0, .35, 0], true)
    wall.material.side = THREE.DoubleSide
    cylinder(.235, .235, .025, 'text-muted', bin, [0, .055, 0])
    const lid = new THREE.Group(); bin.add(lid); lid.position.set(0, .68, -.28)
    cylinder(.305, .305, .035, 'text-muted', lid, [0, 0, .28])
    box(.13, .05, .07, 'text-muted', lid, [0, .045, .28])
    const truck = new THREE.Group(); scene.add(truck); truck.scale.setScalar(1.55)
    box(1.15, .68, .70, 'illustration-paper', truck, [-.25, .79, 0])
    box(.56, .63, .70, 'brand', truck, [.60, .65, 0])
    box(1.8, .10, .64, 'text-muted', truck, [0, .40, 0])
    box(.023, .23, .51, 'surface', truck, [.892, .82, 0])
    for (const side of [-1, 1]) box(.33, .23, .023, 'surface', truck, [.62, .82, side * .362])
    const wheels = []
    for (const x of [-.58, .60]) for (const side of [-1, 1]) {
        const pivot = new THREE.Group(); pivot.position.set(x, .22, side * .39); truck.add(pivot)
        const tire = cylinder(.22, .22, .13, 'text', pivot); tire.rotation.x = Math.PI / 2
        const hub = cylinder(.105, .105, .135, 'border-control', pivot); hub.rotation.x = Math.PI / 2
        box(.14, .028, .015, 'surface', pivot, [0, 0, side * .076])
        wheels.push(pivot)
    }
    const basic = () => ({ actor: [-.2, 0, 0], angle: 0, lean: 0, head: 0, walk: 0, truck: [3.5, 0, -.1], bin: [3.5, 0, .15], lid: 0, left: [-.20, .85, .4], right: [.20, .85, .4], pinch: false, item: [0, .85, .43], itemWorld: false, itemVisible: true, expression: 'neutral' })
    const poseFor = (key, t) => {
        const p = basic()
        if (key === 'waiting') {
            p.actor = [-3.5, 0, 0]; p.truck = [mix(-3.2, 0, ease(t)), 0, -.1]
            p.item = [p.truck[0] - .45, .8, -.1]; p.itemWorld = true; p.itemVisible = false
        } else if (key === 'idle') {
            const approach = phase(t, 0, .24), pickup = phase(t, .24, .44), retreat = phase(t, .44, .66), carry = phase(t, .66, .96), depart = phase(t, .62, .94)
            p.actor = [mix(-2.4, -1.72, approach) + 1.52 * carry, 0, mix(.15, 1.05, retreat) * (1 - carry)]
            p.angle = Math.PI / 2 * (1 - retreat); p.truck = [4.8 * depart, 0, -.1]
            p.walk = Math.sin(t * Math.PI * 8) * .16 * (1 - phase(t, .90, 1))
            if (t < .44) {
                p.item = [mix(-1.0, -1.29, pickup), .85, mix(-.1, .15, pickup)]; p.itemWorld = true
            }
        } else if (key === 'using') {
            const lift = phase(t, 0, .65)
            p.item = [0, .85 + .05 * lift, .43 + .015 * lift]
            p.expression = 'smile'
            p.left = [-.20, p.item[1], .40 + .015 * lift]; p.right = [.20, p.item[1], .40 + .015 * lift]
            p.head = .07 * Math.sin(t * Math.PI * 2)
        } else if (key === 'discarded') {
            const carry = phase(t, .12, .46), drop = phase(t, .48, .76), retract = phase(t, .76, 1)
            p.bin = [.55, 0, .15]; p.lid = -1.5 * phase(t, 0, .15) * (1 - phase(t, .80, 1))
            p.item = vector([-.1, .85, .43], [.55, 1.02, .15], carry); p.itemWorld = true
            p.item[1] -= .70 * drop; p.itemVisible = t < .78
            p.left = vector([-.20, .85, .4], [-.30, .55, .08], carry)
            p.right = vector([.20, .85, .4], [.75, 1.22, .15], carry)
            if (t > .48) p.right = vector([.75, 1.22, .15], [.30, .55, .08], retract)
            p.head = .08 * Math.sin(Math.PI * t)
        } else if (key === 'expired') {
            const inspect = phase(t, 0, .55)
            p.item = [0, .85 - .03 * inspect, .43 + .015 * inspect]
            p.left = [-.20, p.item[1], .40 + .015 * inspect]
            p.right = [.20, p.item[1], .40 + .015 * inspect]
            p.head = .20 * Math.sin(t * Math.PI) + .04 * inspect
            p.expression = 'worry'

        }
        return p
    }
    const apply = p => {
        actor.position.fromArray(p.actor); actor.rotation.set(0, p.angle, p.lean)
        actor.visible = Math.abs(p.actor[0]) < 3
        head.rotation.x = p.head; head.rotation.z = p.pinch ? .08 : 0
        brows[0].rotation.z = p.pinch ? -.25 : 0; brows[1].rotation.z = p.pinch ? .25 : 0
        mouth.rotation.z = 0
        mouth.visible = p.expression === 'neutral'; smile.visible = p.expression === 'smile'; frown.visible = p.expression === 'worry'
        if (p.expression === 'worry') { brows[0].rotation.z = -.22; brows[1].rotation.z = .22; head.rotation.z = .08 }
        legs[0].rotation.x = p.walk; legs[1].rotation.x = -p.walk
        solveArm(arms[0], p.left, p.pinch); solveArm(arms[1], p.right, false)
        actor.updateMatrixWorld(true)
        parcel.position.fromArray(p.item)
        if (!p.itemWorld) actor.localToWorld(parcel.position)
        parcel.quaternion.copy(p.itemWorld ? new THREE.Quaternion() : actor.quaternion)
        parcel.visible = p.itemVisible
        handle.visible = p.pinch
        truck.position.fromArray(p.truck); truck.visible = Math.abs(p.truck[0]) < 3.3
        wheels.forEach(wheel => wheel.rotation.z = -p.truck[0] / .22)
        bin.position.fromArray(p.bin); bin.visible = p.bin[0] < 3; lid.rotation.x = p.lid
    }
    const blend = (a, b, t) => {
        const p = { ...b }
        for (const key of ['actor', 'truck', 'bin', 'left', 'right']) p[key] = vector(a[key], b[key], t)
        if (Math.abs(a.truck[0]) >= 3.3 && Math.abs(b.truck[0]) >= 3.3) p.truck = b.truck
        for (const key of ['angle', 'lean', 'head', 'walk', 'lid']) p[key] = mix(a[key], b[key], t)
        // 物品改以共同的世界座標銜接，避免切換父層造成跳動。
        const worldItem = q => {
            if (q.itemWorld) return q.item
            const v = new THREE.Vector3().fromArray(q.item).applyEuler(new THREE.Euler(0, q.angle, q.lean)).add(new THREE.Vector3().fromArray(q.actor)); return v.toArray()
        }
        p.item = vector(worldItem(a), worldItem(b), t); p.itemWorld = true
        p.itemVisible = t < .5 ? a.itemVisible : b.itemVisible
        return p
    }
    return { apply, poseFor, blend, arms, parcel, actor, bin, truck, wheels, pedestal }
}
