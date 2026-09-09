"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";

interface Bounds {
  width: number;
  height: number;
}

interface Viewport {
  cx: number;
  cy: number;
  /** Screen pixels per plan metre. */
  scale: number;
}

const MAX_ZOOM_FACTOR = 26;
const ANIMATION_MS = 550;
const easeInOutCubic = (t: number) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

export function useMapViewport(bounds: Bounds) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [size, setSize] = useState({ width: 0, height: 0 });
  // Null until the user moves the map: the fitted view is derived, not stored.
  const [viewport, setViewport] = useState<Viewport | null>(null);
  const [isPanning, setIsPanning] = useState(false);

  const animationRef = useRef<number | null>(null);
  const pointersRef = useRef(new Map<number, { x: number; y: number }>());
  const pinchRef = useRef<{ distance: number; scale: number } | null>(null);

  useEffect(() => {
    const element = containerRef.current;
    if (!element) return;
    const observer = new ResizeObserver(([entry]) => {
      setSize({ width: entry.contentRect.width, height: entry.contentRect.height });
    });
    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  const fitScale = useMemo(() => {
    if (!size.width || !size.height) return 0;
    return Math.min(size.width / bounds.width, size.height / bounds.height);
  }, [size, bounds]);

  const fitted = useMemo<Viewport>(
    () => ({ cx: bounds.width / 2, cy: bounds.height / 2, scale: fitScale || 1 }),
    [bounds, fitScale],
  );
  const current = viewport ?? fitted;

  const clamp = useCallback(
    (next: Viewport): Viewport => {
      if (!size.width || !fitScale) return next;
      const scale = Math.min(Math.max(next.scale, fitScale), fitScale * MAX_ZOOM_FACTOR);
      const halfW = size.width / scale / 2;
      const halfH = size.height / scale / 2;
      return {
        cx: halfW * 2 >= bounds.width ? bounds.width / 2 : Math.min(Math.max(next.cx, halfW), bounds.width - halfW),
        cy: halfH * 2 >= bounds.height ? bounds.height / 2 : Math.min(Math.max(next.cy, halfH), bounds.height - halfH),
        scale,
      };
    },
    [size, fitScale, bounds],
  );

  const stopAnimation = useCallback(() => {
    if (animationRef.current !== null) {
      cancelAnimationFrame(animationRef.current);
      animationRef.current = null;
    }
  }, []);

  const viewBox = useMemo(() => {
    const width = size.width ? size.width / current.scale : bounds.width;
    const height = size.height ? size.height / current.scale : bounds.height;
    return { x: current.cx - width / 2, y: current.cy - height / 2, width, height };
  }, [current, size, bounds]);

  /** Zooms while keeping the plan point under the cursor pinned in place. */
  const zoomAt = useCallback(
    (factor: number, clientX?: number, clientY?: number) => {
      stopAnimation();
      const rect = containerRef.current?.getBoundingClientRect();
      setViewport((previous) => {
        const from = previous ?? fitted;
        const nextScale = Math.min(Math.max(from.scale * factor, fitScale), fitScale * MAX_ZOOM_FACTOR);
        if (!rect || clientX === undefined || clientY === undefined) {
          return clamp({ ...from, scale: nextScale });
        }
        const offsetX = clientX - rect.left - rect.width / 2;
        const offsetY = clientY - rect.top - rect.height / 2;
        return clamp({
          cx: from.cx + offsetX / from.scale - offsetX / nextScale,
          cy: from.cy + offsetY / from.scale - offsetY / nextScale,
          scale: nextScale,
        });
      });
    },
    [clamp, fitScale, fitted, stopAnimation],
  );

  const panByPixels = useCallback(
    (dx: number, dy: number) => {
      stopAnimation();
      setViewport((previous) => {
        const from = previous ?? fitted;
        return clamp({ ...from, cx: from.cx - dx / from.scale, cy: from.cy - dy / from.scale });
      });
    },
    [clamp, fitted, stopAnimation],
  );

  const flyTo = useCallback(
    (target: { x: number; y: number }, targetScale?: number) => {
      if (!fitScale) return;
      stopAnimation();
      const from = current;
      const to = clamp({ cx: target.x, cy: target.y, scale: targetScale ?? Math.max(from.scale, fitScale * 8) });
      const start = performance.now();

      const step = (now: number) => {
        const progress = Math.min(1, (now - start) / ANIMATION_MS);
        const eased = easeInOutCubic(progress);
        setViewport({
          cx: from.cx + (to.cx - from.cx) * eased,
          cy: from.cy + (to.cy - from.cy) * eased,
          scale: from.scale + (to.scale - from.scale) * eased,
        });
        animationRef.current = progress < 1 ? requestAnimationFrame(step) : null;
      };
      animationRef.current = requestAnimationFrame(step);
    },
    [clamp, current, fitScale, stopAnimation],
  );

  const fit = useCallback(() => {
    if (fitScale) flyTo({ x: bounds.width / 2, y: bounds.height / 2 }, fitScale);
  }, [flyTo, fitScale, bounds]);

  // Wheel is bound manually so the zoom gesture can cancel page scrolling.
  useEffect(() => {
    const element = containerRef.current;
    if (!element) return;
    const onWheel = (event: WheelEvent) => {
      event.preventDefault();
      const intensity = event.ctrlKey ? 0.015 : 0.0022;
      zoomAt(Math.exp(-event.deltaY * intensity), event.clientX, event.clientY);
    };
    element.addEventListener("wheel", onWheel, { passive: false });
    return () => element.removeEventListener("wheel", onWheel);
  }, [zoomAt]);

  useEffect(() => stopAnimation, [stopAnimation]);

  const pointerHandlers = {
    onPointerDown(event: React.PointerEvent) {
      pointersRef.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
      if (pointersRef.current.size === 1) {
        event.currentTarget.setPointerCapture(event.pointerId);
        setIsPanning(true);
        stopAnimation();
      }
    },
    onPointerMove(event: React.PointerEvent) {
      const pointers = pointersRef.current;
      const previous = pointers.get(event.pointerId);
      if (!previous) return;
      pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

      if (pointers.size >= 2) {
        const [a, b] = [...pointers.values()];
        const distance = Math.hypot(a.x - b.x, a.y - b.y);
        if (!pinchRef.current) {
          pinchRef.current = { distance, scale: current.scale };
        } else if (pinchRef.current.distance > 0) {
          const nextScale = pinchRef.current.scale * (distance / pinchRef.current.distance);
          zoomAt(nextScale / current.scale, (a.x + b.x) / 2, (a.y + b.y) / 2);
        }
        return;
      }

      panByPixels(event.clientX - previous.x, event.clientY - previous.y);
    },
    onPointerUp(event: React.PointerEvent) {
      pointersRef.current.delete(event.pointerId);
      if (pointersRef.current.size < 2) pinchRef.current = null;
      if (pointersRef.current.size === 0) setIsPanning(false);
    },
    onPointerCancel(event: React.PointerEvent) {
      pointersRef.current.delete(event.pointerId);
      pinchRef.current = null;
      setIsPanning(false);
    },
  };

  const onKeyDown = (event: React.KeyboardEvent) => {
    const step = 90;
    const actions: Record<string, () => void> = {
      ArrowUp: () => panByPixels(0, step),
      ArrowDown: () => panByPixels(0, -step),
      ArrowLeft: () => panByPixels(step, 0),
      ArrowRight: () => panByPixels(-step, 0),
      "+": () => zoomAt(1.4),
      "=": () => zoomAt(1.4),
      "-": () => zoomAt(1 / 1.4),
      "0": fit,
    };
    const action = actions[event.key];
    if (action) {
      event.preventDefault();
      action();
    }
  };

  return {
    containerRef,
    viewBox,
    scale: current.scale,
    fitScale,
    zoomFactor: fitScale ? current.scale / fitScale : 1,
    isPanning,
    pointerHandlers,
    onKeyDown,
    zoomAt,
    flyTo,
    fit,
  };
}
