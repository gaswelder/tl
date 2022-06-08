#import strutil
#import opt
#import client.c

int main(int argc, char **argv) {
    bool all = false;
    opt(OPT_BOOL, "a", "show all torrents", &all);
    bool hidden = false;
    opt(OPT_BOOL, "i", "show hidden torrents", &hidden);
    bool order = false;
    opt(OPT_BOOL, "r", "order by seed ratio", &order);
    opt_parse(argc, argv);

    bool showhidden = all || hidden;
    bool showvisible = all || !hidden;

    torr_t *tt = NULL;
    size_t len = 0;
    torrents(&tt, &len);
    if (order) {
        qsort(tt, len, sizeof(torr_t), &cmp);
    }

    FILE *f = fopen("tl.list", "r");

    for (size_t i = 0; i < len; i++) {
        torr_t l = tt[i];

        if (!showhidden && ishidden(f, l.name)) {
            continue;
        }
        if (!showvisible && !ishidden(f, l.name)) {
            continue;
        }

        printf("%s", l.id);
        if (strcmp(l.eta, "Done")) {
            printf(" (%s)", l.done);
        }
        printf("\t%s/%s, r=%s\t%s\n", l.up, l.down, l.ratio, l.name);
    }

    free(tt);
    if (f) fclose(f);
    return 0;
}

bool ishidden(FILE *f, char *name) {
    if (!f) return false;
    rewind(f);
    bool r = false;
    char buf[1000] = {};
    while (fgets(buf, sizeof(buf), f)) {
        if (!strcmp(trim(buf), name)) {
            r = true;
            break;
        }
    }
    return r;
}

int cmp(const void *va, *vb) {
    torr_t *a = (torr_t *) va;
    torr_t *b = (torr_t *) vb;
    float ar = 0;
    float br = 0;
    sscanf(a->ratio, "%f", &ar);
    sscanf(b->ratio, "%f", &br);
    if (ar > br) return -1;
    if (br > ar) return 1;
    return 0;
}

