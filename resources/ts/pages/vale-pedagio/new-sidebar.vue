          <!-- Data/Hora -->
          <div class="mb-1">
              <AppTextField
                v-model="dataHoraFormatted"
                label="Data e Hora"
                prepend-inner-icon="tabler-calendar"
                readonly
                density="compact"
                hide-details
              />
            </div>

            <!-- Lista de Pontos Arrastáveis -->
            <div class="mb-2">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-caption font-weight-medium">Pontos da Rota</span>
                <VBtn
                  icon="tabler-plus"
                  variant="text"
                  color="primary"
                  size="x-small"
                  @click="adicionarPonto"
                />
              </div>
              
              <div class="route-points-container" style="height: 180px; overflow-y: auto;">
                <VList density="compact" class="pa-0">
                  <draggable
                    v-model="pontosRotaPaginados"
                    :itemKey="(item) => item.id"
                    handle=".drag-handle"
                    @end="onDragEnd"
                  >
                    <template #item="{ element, index }">
                      <VListItem
                        class="pa-1 mb-1 route-point-item"
                        :class="{ 'route-point-origin': index === 0, 'route-point-destination': index === pontosRotaPaginados.length - 1 }"
                      >
                        <template #prepend>
                          <div class="d-flex align-center gap-2">
                            <VIcon
                              icon="tabler-grip-vertical"
                              size="14"
                              class="drag-handle cursor-grab"
                              color="grey"
                            />
                            <VAvatar
                              size="20"
                              :color="index === 0 ? 'success' : (index === pontosRotaPaginados.length - 1 ? 'error' : 'primary')"
                            >
                              <span style="font-size: 0.7rem;">{{ (currentPage - 1) * itemsPerPage + index + 1 }}</span>
                            </VAvatar>
                          </div>
                        </template>
                        
                        <VListItemTitle class="text-caption font-weight-medium">
                          {{ element.cidade }}
                        </VListItemTitle>
                        <VListItemSubtitle class="text-caption" style="font-size: 0.65rem;">
                          {{ element.endereco }}
                        </VListItemSubtitle>
                        
                        <template #append>
                          <VBtn
                            icon="tabler-x"
                            variant="text"
                            color="error"
                            size="x-small"
                            @click="removerPonto((currentPage - 1) * itemsPerPage + index)"
                            v-if="pontosRota.length > 2"
                          />
                        </template>
                      </VListItem>
                    </template>
                  </draggable>
                </VList>
              </div>
              
              <!-- Paginação -->
              <div class="d-flex justify-center mt-2" v-if="totalPages > 1">
                <VPagination
                  v-model="currentPage"
                  :length="totalPages"
                  size="small"
                  density="compact"
                  total-visible="3"
                />
              </div>
            </div>

            <!-- Estatísticas da Rota - Melhor contraste -->
            <VRow class="mb-2">
              <VCol cols="6">
                <VCard variant="elevated" color="primary" class="pa-2 text-center stats-card">
                  <VIcon icon="tabler-gas-station" size="16" class="mb-1" color="white" />
                  <div class="text-caption font-weight-bold text-white">R$ {{ combustivel.valor }}</div>
                  <div class="text-caption text-white opacity-80">Combustível</div>
                </VCard>
              </VCol>
              <VCol cols="6">
                <VCard variant="elevated" color="success" class="pa-2 text-center stats-card">
                  <VIcon icon="tabler-car" size="16" class="mb-1" color="white" />
                  <div class="text-caption font-weight-bold text-white">{{ consumo.valor }} KM/L</div>
                  <div class="text-caption text-white opacity-80">Consumo</div>
                </VCard>
              </VCol>
              <VCol cols="6">
                <VCard variant="elevated" color="warning" class="pa-2 text-center stats-card">
                  <VIcon icon="tabler-clock" size="16" class="mb-1" color="white" />
                  <div class="text-caption font-weight-bold text-white">{{ tempo.valor }}h</div>
                  <div class="text-caption text-white opacity-80">Tempo</div>
                </VCard>
              </VCol>
              <VCol cols="6">
                <VCard variant="elevated" color="info" class="pa-2 text-center stats-card">
                  <VIcon icon="tabler-road" size="16" class="mb-1" color="white" />
                  <div class="text-caption font-weight-bold text-white">{{ eixos.valor }} eixos</div>
                  <div class="text-caption text-white opacity-80">Veículo</div>
                </VCard>
              </VCol>
            </VRow>

            <!-- Opções da rota -->
            <VRow class="mb-1">
              <VCol cols="4">
                <VCheckbox
                  v-model="maisOpcoes.priorizarRodovias"
                  label="Rodovias"
                  color="primary"
                  density="compact"
                  hide-details
                />
              </VCol>
              <VCol cols="4">
                <VCheckbox
                  v-model="maisOpcoes.evitarPedagio"
                  label="Evitar pedágio"
                  color="primary"
                  density="compact"
                  hide-details
                />
              </VCol>
              <VCol cols="4">
                <VCheckbox
                  v-model="maisOpcoes.evitarBalsa"
                  label="Evitar balsa"
                  color="primary"
                  density="compact"
                  hide-details
                />
              </VCol>
            </VRow>

            <!-- Tipo de rota + Veículo -->
            <VRow class="mb-1">
              <VCol cols="6">
                <span class="text-caption font-weight-medium d-block mb-1">Tipo de rota</span>
                <VBtnToggle
                  v-model="preferencia"
                  density="compact"
                  variant="outlined"
                  divided
                >
                  <VBtn value="rapida" size="x-small">
                    Rápida
                  </VBtn>
                  <VBtn value="curta" size="x-small">
                    Curta
                  </VBtn>
                  <VBtn value="economica" size="x-small">
                    Econ.
                  </VBtn>
                </VBtnToggle>
              </VCol>
              
              <VCol cols="6">
                <span class="text-caption font-weight-medium d-block mb-1">Veículo</span>
                <VBtnToggle
                  v-model="tipoVeiculo"
                  density="compact"
                  variant="outlined"
                  divided
                >
                  <VBtn value="carro" size="x-small">
                    <VIcon icon="tabler-car" size="12" />
                  </VBtn>
                  <VBtn value="moto" size="x-small">
                    <VIcon icon="tabler-motorbike" size="12" />
                  </VBtn>
                  <VBtn value="caminhao" size="x-small">
                    <VIcon icon="tabler-truck" size="12" />
                  </VBtn>
                  <VBtn value="van" size="x-small">
                    <VIcon icon="tabler-bus" size="12" />
                  </VBtn>
                </VBtnToggle>
              </VCol>
            </VRow>

            <!-- Botão Calcular -->
            <div class="mt-1">
              <VBtn
                color="warning"
                size="small"
                block
                @click="calcularRota"
                :loading="loading"
                elevation="2"
                class="font-weight-bold"
              >
                <VIcon icon="tabler-calculator" start />
                Calcular Rota
              </VBtn>
            </div>