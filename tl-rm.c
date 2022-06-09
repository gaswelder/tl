#import client.c
#import os/exec

int main(int argc, char **argv) {
    if (argc < 2) {
        fprintf(stderr, "usage: %s <torrent-id> ...\n", argv[0]);
        return 1;
    }

    for (char **id = argv + 1; *id; id++) {
        char dir[1000] = {};
        if (!tl_getval(*id, "Location", dir, sizeof(dir))) {
            fprintf(stderr, "failed to get location for torrent %s\n", *id);
            continue;
        }
        char name[1000] = {};
        if (!tl_getval(*id, "Name", name, sizeof(name))) {
            fprintf(stderr, "failed to get name for torrent %s\n", *id);
            continue;
        }

        tl_rm(*id);

        char *path = newstr("%s/%s", dir, name);
        char *newpath = newstr("%s/__/%s", dir, name);
        if (rename(path, newpath) < 0) {
            fprintf(stderr, "failed to move torrent to '%s': %s\n", newpath, strerror(errno));
        }
        free(path);
        free(newpath);
    }

    return 0;
}
