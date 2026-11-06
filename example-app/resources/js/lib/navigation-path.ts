import type { NavItem } from '@/types';
import { toUrl } from './utils';
const pathname = (url: string) =>
    new URL(url, 'http://localhost').pathname.replace(/\/$/, '') || '/';
export function matchesNavigationPath(
    currentUrl: string,
    parentUrl: string,
): boolean {
    const current = pathname(currentUrl),
        parent = pathname(parentUrl);
    return current === parent || current.startsWith(parent + '/');
}
export function getActiveNavIndex(
    currentUrl: string,
    items: NavItem[],
): number {
    let selected = -1,
        length = -1;
    items.forEach((item, index) =>
        (item.matchPaths ?? [toUrl(item.href)]).forEach((path) => {
            if (
                matchesNavigationPath(currentUrl, path) &&
                path.length > length
            ) {
                selected = index;
                length = path.length;
            }
        }),
    );
    return selected;
}
