/**
 * Login page only: WebGL smokey background driven by --color-primary (see resources/css/app.css).
 * No dependency on app.js (Alpine/Echo/telephony).
 */

const vertexSmokeySource = `
  attribute vec4 a_position;
  void main() {
    gl_Position = a_position;
  }
`;

const fragmentSmokeySource = `
precision mediump float;

uniform vec2 iResolution;
uniform float iTime;
uniform vec2 iMouse;
uniform vec3 u_color;

void mainImage(out vec4 fragColor, in vec2 fragCoord){
    vec2 uv = fragCoord / iResolution;
    vec2 centeredUV = (2.0 * fragCoord - iResolution.xy) / min(iResolution.x, iResolution.y);

    float time = iTime * 0.5;

    vec2 mouse = iMouse / iResolution;
    vec2 rippleCenter = 2.0 * mouse - 1.0;

    vec2 distortion = centeredUV;
    for (float i = 1.0; i < 8.0; i++) {
        distortion.x += 0.5 / i * cos(i * 2.0 * distortion.y + time + rippleCenter.x * 3.1415);
        distortion.y += 0.5 / i * cos(i * 2.0 * distortion.x + time + rippleCenter.y * 3.1415);
    }

    float wave = abs(sin(distortion.x + distortion.y + time));
    float glow = smoothstep(0.9, 0.2, wave);

    fragColor = vec4(u_color * glow, 1.0);
}

void main() {
    mainImage(gl_FragColor, gl_FragCoord.xy);
}
`;

function parseCssColorToRgb01(value) {
    const v = (value || '').trim();
    if (!v) return [0.89, 0.12, 0.55];
    if (v.startsWith('#')) {
        const hex = v.slice(1);
        if (hex.length === 3) {
            const r = parseInt(hex[0] + hex[0], 16) / 255;
            const g = parseInt(hex[1] + hex[1], 16) / 255;
            const b = parseInt(hex[2] + hex[2], 16) / 255;
            return [r, g, b];
        }
        if (hex.length >= 6) {
            const r = parseInt(hex.slice(0, 2), 16) / 255;
            const g = parseInt(hex.slice(2, 4), 16) / 255;
            const b = parseInt(hex.slice(4, 6), 16) / 255;
            return [r, g, b];
        }
    }
    const m = v.match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/i);
    if (m) {
        return [parseFloat(m[1]) / 255, parseFloat(m[2]) / 255, parseFloat(m[3]) / 255];
    }
    return [0.89, 0.12, 0.55];
}

function getPrimaryRgb01() {
    const raw = getComputedStyle(document.documentElement).getPropertyValue('--color-primary');
    return parseCssColorToRgb01(raw);
}

function initSmokeyBackground() {
    const canvas = document.getElementById('login-smokey-canvas');
    if (!canvas) return () => {};

    const gl = canvas.getContext('webgl');
    if (!gl) {
        console.warn('[login-page] WebGL not available');
        return () => {};
    }

    const compileShader = (type, source) => {
        const shader = gl.createShader(type);
        if (!shader) return null;
        gl.shaderSource(shader, source);
        gl.compileShader(shader);
        if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
            console.warn('[login-page] Shader compile:', gl.getShaderInfoLog(shader));
            gl.deleteShader(shader);
            return null;
        }
        return shader;
    };

    const vertexShader = compileShader(gl.VERTEX_SHADER, vertexSmokeySource);
    const fragmentShader = compileShader(gl.FRAGMENT_SHADER, fragmentSmokeySource);
    if (!vertexShader || !fragmentShader) return () => {};

    const program = gl.createProgram();
    if (!program) return () => {};
    gl.attachShader(program, vertexShader);
    gl.attachShader(program, fragmentShader);
    gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
        console.warn('[login-page] Program link:', gl.getProgramInfoLog(program));
        return () => {};
    }
    gl.useProgram(program);

    const positionBuffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]), gl.STATIC_DRAW);

    const positionLocation = gl.getAttribLocation(program, 'a_position');
    gl.enableVertexAttribArray(positionLocation);
    gl.vertexAttribPointer(positionLocation, 2, gl.FLOAT, false, 0, 0);

    const iResolutionLocation = gl.getUniformLocation(program, 'iResolution');
    const iTimeLocation = gl.getUniformLocation(program, 'iTime');
    const iMouseLocation = gl.getUniformLocation(program, 'iMouse');
    const uColorLocation = gl.getUniformLocation(program, 'u_color');

    let startTime = Date.now();
    let rafId = 0;
    const mount = canvas.parentElement;
    let width = 0;
    let height = 0;

    const setUniformColor = () => {
        gl.useProgram(program);
        const [r, g, b] = getPrimaryRgb01();
        gl.uniform3f(uColorLocation, r, g, b);
    };

    const render = () => {
        const w = mount ? mount.clientWidth : canvas.clientWidth;
        const h = mount ? mount.clientHeight : canvas.clientHeight;
        if (w < 1 || h < 1) {
            rafId = requestAnimationFrame(render);
            return;
        }
        if (w !== width || h !== height) {
            width = w;
            height = h;
            canvas.width = w;
            canvas.height = h;
            gl.viewport(0, 0, w, h);
        }

        const currentTime = (Date.now() - startTime) / 1000;
        gl.useProgram(program);
        gl.uniform2f(iResolutionLocation, width, height);
        gl.uniform1f(iTimeLocation, currentTime);
        gl.uniform2f(iMouseLocation, width / 2, height / 2);

        gl.drawArrays(gl.TRIANGLES, 0, 6);
        rafId = requestAnimationFrame(render);
    };

    setUniformColor();
    render();

    const bumpSize = () => {
        width = 0;
        height = 0;
    };
    window.addEventListener('resize', bumpSize);
    const ro = typeof ResizeObserver !== 'undefined' && mount ? new ResizeObserver(bumpSize) : null;
    if (ro) ro.observe(mount);

    const observer = new MutationObserver(() => {
        setUniformColor();
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    return () => {
        window.removeEventListener('resize', bumpSize);
        if (ro) ro.disconnect();
        observer.disconnect();
        if (rafId) cancelAnimationFrame(rafId);
        gl.deleteProgram(program);
        gl.deleteShader(vertexShader);
        gl.deleteShader(fragmentShader);
        gl.deleteBuffer(positionBuffer);
    };
}

const teardown = initSmokeyBackground();
window.addEventListener('beforeunload', () => {
    if (typeof teardown === 'function') teardown();
});
