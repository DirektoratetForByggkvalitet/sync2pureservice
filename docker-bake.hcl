group "default" {
  targets = ["worker"]
}
target "worker" {
  dockerfile = "Dockerfile"
  platforms = ["linux/amd64", "linux/arm64"]
  tags = ["dibk/sync2pureservice:latest", "dibk/sync2pureservice:alpine"]
}
