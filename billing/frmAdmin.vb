Public Class frmAdmin


    Private Sub btnOR_Click(sender As Object, e As EventArgs) Handles btnOR.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to add this or numbers?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            logor()
            viewlog()
            AdminorGen()
            AdminORview()
        End If
    End Sub


    Private Sub frmAdmin_Disposed(sender As Object, e As EventArgs) Handles Me.Disposed

    End Sub

    Private Sub frmAdmin_Load(sender As Object, e As EventArgs) Handles MyBase.Load

        'cbTarrifServiceCode.Text = ""
        ExtendedMethods.DoubleBuffered(dgAccounts, True)
        ExtendedMethods.DoubleBuffered(dgAdminORtrans, True)
        ExtendedMethods.DoubleBuffered(dgAdminBilltrans, True)
        ExtendedMethods.DoubleBuffered(dgOrno, True)
        ExtendedMethods.DoubleBuffered(dgBillno, True)
        ExtendedMethods.DoubleBuffered(dgAdminUser, True)
        ExtendedMethods.DoubleBuffered(dgAdminService, True)
        ExtendedMethods.DoubleBuffered(dgTarrif, True)
        AdminORview()
        AdminBillview()
        AdminUserview()
        AdminBillTransview()
        AdminOrTransview()
        AdminAccountView()
        mysettings()
        AdminServiceView()
        AdminTarrifView()
        servicecode()
        aliasname()
        counterBill()
        viewlog()
    End Sub




    Private Sub txtOrsearch_TextChanged(sender As Object, e As EventArgs) Handles txtOrsearch.TextChanged
        AdminOrSearch()
    End Sub

    Private Sub btnordelete_Click(sender As Object, e As EventArgs) Handles btnordelete.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this OR #: '" & txtTempOr.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            AdminOrDel()
            AdminORview()
        End If
    End Sub


    Private Sub btnOrupdate_Click(sender As Object, e As EventArgs) Handles btnOrupdate.Click
        AdminOrUpdate()
        AdminORview()
    End Sub





    Private Sub btnBillUpdate_Click(sender As Object, e As EventArgs) Handles btnBillUpdate.Click
        AdminBillUpdate()
        AdminBillview()
    End Sub

    Private Sub btnBill_Click(sender As Object, e As EventArgs) Handles btnBill.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to add this bill numbers?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            logbill()
            viewlog()
            AdminbillGen()
            AdminBillview()
        End If
    End Sub

    Private Sub txtBillSearch_TextChanged(sender As Object, e As EventArgs) Handles txtBillSearch.TextChanged
        AdminBillSearch()
    End Sub

    Private Sub btnBillDel_Click(sender As Object, e As EventArgs) Handles btnBillDel.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this Bill #: '" & txtTempBill.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            AdminBillDel()
            AdminBillview()
        End If
    End Sub





    Private Sub btnUserDel_Click(sender As Object, e As EventArgs) Handles btnUserDel.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this User: '" & txtTempUser.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            AdminUserDel()
            AdminUserview()
        End If
    End Sub

    Private Sub btnUserUpdate_Click(sender As Object, e As EventArgs) Handles btnUserUpdate.Click
        AdminUserUpdate()
        AdminUserview()
    End Sub

    Private Sub btnRegister_Click(sender As Object, e As EventArgs) Handles btnRegister.Click
        AdminUserReg()
        AdminUserview()
    End Sub

    Private Sub txtUserSearch_TextChanged(sender As Object, e As EventArgs) Handles txtUserSearch.TextChanged
        AdminUserSearch()
    End Sub

    Private Sub dgAdminBilltrans_CellContentClick(sender As Object, e As DataGridViewCellEventArgs)

    End Sub




    Private Sub btnBillTransUpdate_Click(sender As Object, e As EventArgs) Handles btnBillTransUpdate.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Update this Bill #: '" & lblBillno.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            billupdatelog()
            viewlog()
            AdminBillTransUpdate()
            AdminBillTransview()
        End If
    End Sub

    Private Sub txtBillTransno_TextChanged(sender As Object, e As EventArgs) Handles txtBillTransno.TextChanged
        AdminBillTranSearch()
    End Sub

    Private Sub dgAdminORtrans_CellContentClick(sender As Object, e As DataGridViewCellEventArgs)

    End Sub



    Private Sub btnOrTransUpdate_Click(sender As Object, e As EventArgs) Handles btnOrTransUpdate.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Update this OR #: '" & lblOrTransno.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            orupdatelog()
            viewlog()
            AdminOrTransUpdate()
            AdminOrTransview()
        End If
    End Sub

    Private Sub txtOrTransno_TextChanged(sender As Object, e As EventArgs) Handles txtOrTransno.TextChanged
        AdminOrTransSearch()
    End Sub




    Private Sub txtAccountSearch_MaskInputRejected(sender As Object, e As MaskInputRejectedEventArgs)

    End Sub




    Private Sub dgBillno_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgBillno.CellContentClick

    End Sub


    Private Sub dgAdminUser_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgAdminUser.CellContentClick

    End Sub

    Private Sub dgAdminUser_SelectionChanged(sender As Object, e As EventArgs) Handles dgAdminUser.SelectionChanged
        AdmindgUserSel()
    End Sub

    Private Sub dgBillno_SelectionChanged(sender As Object, e As EventArgs) Handles dgBillno.SelectionChanged
        AdmindgBillSel()
    End Sub

    Private Sub dgOrno_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgOrno.CellContentClick

    End Sub

    Private Sub dgOrno_SelectionChanged(sender As Object, e As EventArgs) Handles dgOrno.SelectionChanged
        AdmindgOrSel()
    End Sub

    Private Sub dgAdminBilltrans_CellContentClick_1(sender As Object, e As DataGridViewCellEventArgs) Handles dgAdminBilltrans.CellContentClick

    End Sub

    Private Sub dgAdminBilltrans_SelectionChanged(sender As Object, e As EventArgs) Handles dgAdminBilltrans.SelectionChanged
        AdminBillTransSel()
    End Sub

    Private Sub dgAdminORtrans_CellContentClick_1(sender As Object, e As DataGridViewCellEventArgs) Handles dgAdminORtrans.CellContentClick

    End Sub

    Private Sub dgAdminORtrans_SelectionChanged(sender As Object, e As EventArgs) Handles dgAdminORtrans.SelectionChanged
        AdminOrTransSel()
    End Sub

    Private Sub dgAccounts_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgAccounts.CellContentClick

    End Sub

    Private Sub txtAdminAccSearch_TextChanged_1(sender As Object, e As EventArgs) Handles txtAdminAccSearch.TextChanged
        AdminAccountSearch()
    End Sub

    Private Sub dgAccounts_SelectionChanged(sender As Object, e As EventArgs) Handles dgAccounts.SelectionChanged
        AdminAccSel()
    End Sub


    Private Sub btnAccUpdate_Click(sender As Object, e As EventArgs) Handles btnAccUpdate.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Update this Account code: '" & tempcode.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            AdminAccUpdate()
            AdminAccountView()
        End If
    End Sub


    Private Sub btnAccDel_Click(sender As Object, e As EventArgs) Handles btnAccDel.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this Account code: '" & tempcode.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            AdminAccDel()
            AdminAccountView()
        End If
    End Sub

    Private Sub btnRegAcc_Click(sender As Object, e As EventArgs) Handles btnRegAcc.Click
        AdminAccReg()
        AdminAccountView()
    End Sub

    Private Sub btnSettingUpdate_Click(sender As Object, e As EventArgs) Handles btnSettingUpdate.Click
        AdminSettUpdate()
        mysettings()
    End Sub

    Private Sub btnAddService_Click_1(sender As Object, e As EventArgs) Handles btnAddService.Click
        AdminAddService()
        AdminServiceView()
        servicecode()
    End Sub

    Private Sub txtServiceSearch_TextChanged(sender As Object, e As EventArgs) Handles txtServiceSearch.TextChanged
        AdminServiceSearch()
    End Sub

    Private Sub dgAdminService_SelectionChanged(sender As Object, e As EventArgs) Handles dgAdminService.SelectionChanged
        AdminServiceSel()
    End Sub

    Private Sub btnServiceUpdate_Click(sender As Object, e As EventArgs) Handles btnServiceUpdate.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Update this Service: '" & Tempdesc.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            AdminServiceUpdate()
            AdminServiceView()
        End If
    End Sub

    Private Sub btnServiceDel_Click(sender As Object, e As EventArgs) Handles btnServiceDel.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this Service: '" & Tempdesc.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            AdminServiceDel()
            AdminServiceView()
        End If
    End Sub

    Private Sub TextBox5_KeyPress(sender As Object, e As KeyPressEventArgs) Handles darrastre.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub TextBox5_TextChanged(sender As Object, e As EventArgs) Handles darrastre.TextChanged

    End Sub

    Private Sub TextBox6_KeyPress(sender As Object, e As KeyPressEventArgs) Handles dstevedor.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub TextBox6_TextChanged(sender As Object, e As EventArgs) Handles dstevedor.TextChanged

    End Sub

    Private Sub TextBox7_KeyPress(sender As Object, e As KeyPressEventArgs) Handles dother.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub TextBox7_TextChanged(sender As Object, e As EventArgs) Handles dother.TextChanged

    End Sub

    Private Sub TextBox10_KeyPress(sender As Object, e As KeyPressEventArgs) Handles farrastre.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub TextBox10_TextChanged(sender As Object, e As EventArgs) Handles farrastre.TextChanged

    End Sub

    Private Sub TextBox9_KeyPress(sender As Object, e As KeyPressEventArgs) Handles fstevedor.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub TextBox9_TextChanged(sender As Object, e As EventArgs) Handles fstevedor.TextChanged

    End Sub

    Private Sub TextBox8_KeyPress(sender As Object, e As KeyPressEventArgs) Handles fother.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub TextBox8_TextChanged(sender As Object, e As EventArgs) Handles fother.TextChanged

    End Sub

    Private Sub txtTarrifSearch_TextChanged(sender As Object, e As EventArgs) Handles txtTarrifSearch.TextChanged
        AdminTarrifSearch()
    End Sub

    Private Sub dgTarrif_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgTarrif.CellContentClick

    End Sub

    Private Sub dgTarrif_SelectionChanged(sender As Object, e As EventArgs) Handles dgTarrif.SelectionChanged
        AdminTarrifSel()
    End Sub

    Private Sub btnClear_Click(sender As Object, e As EventArgs) Handles btnClear.Click
        txtTarrifCode.Text = ""
        txtTarrifDesc.Text = ""
        txtTarrifBasis.Text = ""
        txtTarrifUnit.Text = ""
        cbTarrifClass.Text = ""
        darrastre.Text = ""
        dstevedor.Text = ""
        dother.Text = ""
        farrastre.Text = ""
        fstevedor.Text = ""
        fother.Text = ""
        cbTarrifServiceCode.Text = ""
        cbAlias.Text = ""
    End Sub

    Private Sub btnTarrifAdd_Click(sender As Object, e As EventArgs) Handles btnTarrifAdd.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Add this New Tarrif: '" & txtTarrifDesc.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            AdminTarrifAdd()
            AdminTarrifView()
        End If
    End Sub

    Private Sub btnTarrifUpdate_Click(sender As Object, e As EventArgs) Handles btnTarrifUpdate.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Update this Tarrif code: '" & temptariffcode.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            AdminTarrifUpdate()
            AdminTarrifView()
        End If
    End Sub

    Private Sub btnTarrifDelete_Click(sender As Object, e As EventArgs) Handles btnTarrifDelete.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this Tarrif code: '" & temptariffcode.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            AdminTarrifDel()
            AdminTarrifView()
        End If
    End Sub

    Private Sub Button1_Click(sender As Object, e As EventArgs) Handles Button1.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Log out?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            Me.Hide()
            frmLogin.Show()
            With frmLogin
                .txtUname.Text = "Username"
                .txtPass.Text = "Password"
                .txtUname.Focus()
            End With
        End If
    End Sub

    Private Sub btnBillTransDel_Click(sender As Object, e As EventArgs) Handles btnBillTransDel.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this Bill #: '" & lblBillno.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            billdeletelog()
            viewlog()
            AdminBillTransDel()
            AdminBillTransview()
        End If
    End Sub

    Private Sub btnOrTransDel_Click(sender As Object, e As EventArgs) Handles btnOrTransDel.Click
        Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this OR #: '" & lblOrTransno.Text & "'?", "Caution!", MessageBoxButtons.YesNo)
        If result = DialogResult.Yes Then
            ordeletelog()
            viewlog()
            AdminOrTransDel()
            AdminOrTransview()
        End If
    End Sub


    Private Sub btnBillExp_Click(sender As Object, e As EventArgs) Handles btnBillExp.Click
        ExportBill()
    End Sub


    Private Sub btnOrExp_Click(sender As Object, e As EventArgs) Handles btnOrExp.Click
        ExportOr()
    End Sub

    Private Sub Timer1_Tick(sender As Object, e As EventArgs)

    End Sub

    Private Sub cbTarrifServiceCode_GotFocus(sender As Object, e As EventArgs) Handles cbTarrifServiceCode.GotFocus
        aliasname()
    End Sub


    Private Sub cbTarrifServiceCode_SelectedIndexChanged(sender As Object, e As EventArgs) Handles cbTarrifServiceCode.SelectedIndexChanged

    End Sub

    Private Sub cbAlias_GotFocus(sender As Object, e As EventArgs) Handles cbAlias.GotFocus
        aliasname()
    End Sub

    Private Sub cbAlias_SelectedIndexChanged(sender As Object, e As EventArgs) Handles cbAlias.SelectedIndexChanged

    End Sub

    Private Sub Button2_Click(sender As Object, e As EventArgs) Handles Button2.Click
        counterBill()
    End Sub

    Private Sub btnPrint_Click(sender As Object, e As EventArgs) Handles btnPrint.Click
        frmRevenue.Show()
    End Sub

    Private Sub txtPeriod_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtPeriod.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) And (Asc(e.KeyChar) < 46 Or Asc(e.KeyChar) > 46) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtPeriod_TextChanged(sender As Object, e As EventArgs) Handles txtPeriod.TextChanged
        
    End Sub

    Private Sub Button3_Click(sender As Object, e As EventArgs) Handles Button3.Click
        fix()
    End Sub

    Private Sub Button4_Click(sender As Object, e As EventArgs) Handles Button4.Click
        frmVolume.ShowDialog()
    End Sub

    Private Sub txtBFrom_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtBFrom.KeyPress

    End Sub

    Private Sub txtBFrom_TextChanged(sender As Object, e As EventArgs) Handles txtBFrom.TextChanged

    End Sub

    Private Sub txtBillno_TextChanged(sender As Object, e As EventArgs) Handles txtBillno.TextChanged

    End Sub

    Private Sub dgBillno_SizeChanged(sender As Object, e As EventArgs) Handles dgBillno.SizeChanged

    End Sub
End Class
