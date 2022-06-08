#import client.c

int main(int argc, char **argv) {
    if (argc < 2) {
        fprintf(stderr, "usage: %s <torrent id> ...\n", argv[0]);
        return 1;
    }

    FILE *f = fopen("tl.list", "r");
    if (!f) {
        fprintf(stderr, "nothing to unhide (tl.list doesn't exist)\n");
        return 1;
    }
    FILE *tmp = fopen("tl.list.tmp", "w");
    if (!tmp) {
        fprintf(stderr, "failed to open tl.list.tmp: %s\n", strerror(errno));
        return 1;
    }

    torr_t *tt = NULL;
    size_t len = 0;
    torrents(&tt, &len);

    show(tt, len, f, tmp, argv + 1);

    fclose(tmp);
    fclose(f);
    free(tt);
    return 0;
}

void show(torr_t *tt, size_t len, FILE *f, *tmp, char **ids) {
    char buf[1000] = {};
    while (fgets(buf, sizeof(buf), f)) {
        char *name = trim(buf);
        /*
         * Find the torrent with this name
         */
        torr_t *t = NULL;
        for (size_t i = 0; i < len; i++) {
            if (!strcmp(tt[i].name, name)) {
                t = tt + i;
                printf("name %s is id %s\n", name, t->id);
                break;
            }
        }

        /*
         * If torrent not found, GC
         */
        if (t == NULL) continue;

        /*
         * If id matches, omit this line.
         * Otherwise keep it.
         */
        if (contains(ids, t->id)) continue;
        fprintf(tmp, "%s\n", name);
    }
}

bool contains(char **ids, char *id) {
    for (char **p = ids; *p; p++) {
        printf("contains %s %s?\n", *p, id);
        if (!strcmp(*p, id)) {
            return true;
        }
    }
    return false;
}
