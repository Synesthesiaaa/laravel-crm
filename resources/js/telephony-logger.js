const LEVEL_STYLE = {
    debug: 'color:#64748b',
    info: 'color:#2563eb',
    warn: 'color:#d97706',
    error: 'color:#dc2626',
    event: 'color:#7c3aed',
};

function write(level, component, message, context = {}) {
    const now = new Date().toISOString();
    const prefix = `[TELEPHONY][${level.toUpperCase()}][${component}]`;
    const style = LEVEL_STYLE[level] || '';

    if (level === 'error') {
        console.error(`%c${prefix} ${message}`, style, { timestamp: now, ...context });
    } else if (level === 'warn') {
        console.warn(`%c${prefix} ${message}`, style, { timestamp: now, ...context });
    } else {
        console.log(`%c${prefix} ${message}`, style, { timestamp: now, ...context });
    }
}

const TelephonyLogger = {
    debug(component, message, context = {}) {
        write('debug', component, message, context);
    },
    info(component, message, context = {}) {
        write('info', component, message, context);
    },
    warn(component, message, context = {}) {
        write('warn', component, message, context);
    },
    error(component, message, context = {}) {
        write('error', component, message, context);
    },
    event(component, eventType, message, context = {}) {
        write('event', component, message, { ...context, eventType });
    },
};

window.TelephonyLogger = TelephonyLogger;

export default TelephonyLogger;
