import type { PropsWithChildren } from 'react';
import { Frame } from '@shopify/polaris';
import { AppNavigation } from './AppNavigation';
import { AppTopBar } from './AppTopBar';

export function AppLayout({ children }: PropsWithChildren) {
  return (
    <Frame navigation={<AppNavigation />} topBar={<AppTopBar />}>
      {children}
    </Frame>
  );
}
