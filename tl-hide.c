#import client.c

int main(int argc, char **argv) {
    if (argc < 2) {
        fprintf(stderr, "usage: %s <torrent id> ...\n", argv[0]);
        return 1;
    }

    torr_t *tt = NULL;
    size_t len = 0;
    torrents(&tt, &len);
    FILE *f = fopen("tl.list", "a+");
    for (char **id = argv + 1; *id; id++) {
        for (size_t i = 0; i < len; i++) {
            torr_t l = tt[i];
            if (!strcmp(*id, l.id)) {
                fprintf(f, "%s\n", l.name);
                break;
            }
        }
    }

    free(tt);
    fclose(f);
    return 0;
}
