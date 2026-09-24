import '@testing-library/jest-dom';

// jsdom doesn't implement matchMedia — Polaris' responsive breakpoint
// utilities call it on every render, so every test using Polaris
// components needs this polyfill in place before any component mounts.
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: (query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  }),
});

// Polaris' Popover (used inside Filters/IndexTable/Select) observes
// element size changes — jsdom has no layout engine and doesn't
// implement ResizeObserver at all.
class ResizeObserverStub {
  observe() {}
  unobserve() {}
  disconnect() {}
}
(window as unknown as { ResizeObserver: typeof ResizeObserverStub }).ResizeObserver = ResizeObserverStub;

// jsdom has no real layout engine — every element reports a 0x0
// bounding box. Polaris' Tabs component uses this to decide how many
// tabs fit before collapsing the rest into a "More views" overflow
// menu, and with zero width it collapses everything, making tabs
// unreachable as plain buttons in tests. A generous fixed width keeps
// Tabs (and anything else measuring element size) in its normal,
// uncollapsed state during tests.
Element.prototype.getBoundingClientRect = () => ({
  width: 1024,
  height: 768,
  top: 0,
  left: 0,
  bottom: 768,
  right: 1024,
  x: 0,
  y: 0,
  toJSON: () => {},
});
