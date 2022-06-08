#import os/exec
#import parsebuf
#import strutil
#import opt

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

void torrents(torr_t **items, size_t *size) {
    /*
     * Call the torrents client
     */
    char *argv[] = {"/usr/bin/ssh", "pi", "transmission-remote", "-n", "transmission:transmission", "-l", NULL};
    char **env = {NULL};
    pipe_t pipe = exec_makepipe();
    exec_t *child = exec("/usr/bin/ssh", argv, env, stdin, pipe.write, stderr);
    fclose(pipe.write);

    /*
     * Read and parse the output
     */
    size_t len = 0;
    size_t cap = 1;
    torr_t *list = malloc(cap * sizeof(torr_t));
    char buf[1000] = {};
    while (fgets(buf, sizeof(buf), pipe.read)) {
        char *line = trim(buf);
        torr_t t = parseline(line);
        if (!strcmp(t.id, "ID") || !strcmp(t.id, "Sum:")) {
            continue;
        }
        if (len + 1 > cap) {
            cap *= 2;
            list = realloc(list, cap * sizeof(torr_t));
        }
        list[len] = t;
        len++;
    }

    /*
     * Close the client
     */
    int status = 0;
    if (!exec_wait(child, &status)) {
        fprintf(stderr, "wait failed: %s\n", strerror(errno));
    }

    /*
     * Return the list
     */
    *items = list;
    *size = len;
}

typedef {
     char id[8];
     char done[8];
     char have[32];
     char eta[32];
     char up[32];
     char down[32];
     char ratio[32];
     char status[32];
     char name[1000];
} torr_t;

torr_t parseline(char *line)
{
	// ID     Done       Have  ETA           Up    Down  Ratio  Status       Name
	// 1      100%   10.48 MB  Done         0.0     0.0    2.9  Idle         John Fowles
	// 41     n/a        None  Unknown      0.0     0.0   None  Idle          Books
	// 38*    n/a        None  Unknown      0.0     0.0   None  Stopped       name

    parsebuf_t *b = buf_new(line);

    torr_t t = {};
    buf_skip_set(b, " \t");
    // ID: 1 | 38*
    word(b, t.id, sizeof(t.id));
    // "Done": 20% | n/a
    word(b, t.done, sizeof(t.done));
    // "Have": 10.48 MB | None
    size(b, t.have, sizeof(t.have));
    // "ETA": 10 m | Done | Unknown
    size(b, t.eta, sizeof(t.eta));
    // up, down, ratio: 1.2 | None
    word(b, t.up, sizeof(t.up));
    word(b, t.down, sizeof(t.down));
    word(b, t.ratio, sizeof(t.ratio));
    // "Status": Idle | Stopped | Up & Down
    if (buf_skip_literal(b, "Up & Down")) {
        strcpy(t.status, "Up & Down");
    }
    else word(b, t.status, sizeof(t.status));
    
    // "Name": the remainder
    rest(b, t.name, sizeof(t.name));
    return t;
}

void word(parsebuf_t *b, char *p, size_t n) {
    while (buf_more(b) && buf_peek(b) != ' ') {
        if (n == 0) return;
        n--;
        *p++ = buf_get(b);
    }
    spaces(b);
}

void spaces(parsebuf_t *b) {
    while (buf_more(b) && buf_peek(b) == ' ') {
        buf_get(b);
    }
}

void size(parsebuf_t *b, char *p, size_t n) {
    if (isdigit(buf_peek(b))) {
        while (buf_more(b) && buf_peek(b) != ' ') {
            if (n == 0) return;
            n--;
            *p++ = buf_get(b);
        }
        spaces(b);
        word(b, p, n);
    } else {
        word(b, p, n);
    }
}

void rest(parsebuf_t *b, char *p, size_t n) {
    while (buf_more(b)) {
        if (n == 0) return;
        n--;
        *p++ = buf_get(b);
    }
}
